<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Agent\Provider\OpenAIRealtime;

use NeuronAI\Exceptions\ProviderException;

/**
 * Lightweight native PHP WebSocket client using stream_socket_client.
 *
 * Implements just enough of RFC 6455 for text-frame communication with
 * the OpenAI Realtime API. No external dependencies required — uses only
 * PHP's built-in stream and OpenSSL extensions.
 *
 * Supports:
 * - WSS (TLS) connections
 * - Text frame send/receive
 * - Ping/pong handling
 * - Connection close handshake
 * - Configurable timeouts
 */
class NativeWebSocketClient
{
    /** @var resource|null */
    private $socket = null;

    private bool $connected = false;

    /**
     * @param string $url WebSocket URL (wss://...)
     * @param array<string, string> $headers Extra HTTP headers for the upgrade request
     * @param int $timeout Read/write timeout in seconds
     */
    public function __construct(
        private readonly string $url,
        private readonly array $headers = [],
        private readonly int $timeout = 30,
    ) {}

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Open the WebSocket connection.
     *
     * @throws ProviderException
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $parsed = parse_url($this->url);
        if ($parsed === false) {
            throw new ProviderException("Invalid WebSocket URL: {$this->url}");
        }

        $scheme = $parsed['scheme'] ?? 'wss';
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? ($scheme === 'wss' ? 443 : 80);
        $path = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        if (empty($host)) {
            throw new ProviderException("No host in WebSocket URL: {$this->url}");
        }

        $transport = ($scheme === 'wss') ? 'ssl' : 'tcp';
        $address = "{$transport}://{$host}:{$port}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($this->socket === false || !is_resource($this->socket)) {
            $this->socket = null;
            throw new ProviderException(
                "WebSocket connection failed to {$address}: [{$errno}] {$errstr}"
            );
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Perform WebSocket upgrade handshake
        $this->performHandshake($host, $port, $path);
        $this->connected = true;
    }

    /**
     * Send a text message over the WebSocket.
     *
     * @throws ProviderException
     */
    public function send(string $data): void
    {
        $this->assertConnected();
        $frame = $this->encodeFrame($data, 0x1); // 0x1 = text frame
        $this->writeRaw($frame);
    }

    /**
     * Receive the next text message from the WebSocket.
     * Automatically handles ping/pong and ignores non-text frames.
     *
     * @throws ProviderException on timeout, connection close, or error
     */
    public function receive(): string
    {
        $this->assertConnected();

        while (true) {
            $frame = $this->readFrame();

            if ($frame === null) {
                throw new ProviderException('WebSocket connection closed unexpectedly');
            }

            switch ($frame['opcode']) {
                case 0x1: // Text frame
                    return $frame['payload'];

                case 0x2: // Binary frame — treat as text for JSON APIs
                    return $frame['payload'];

                case 0x8: // Close frame
                    $this->connected = false;
                    $this->closeSocket();
                    throw new ProviderException('WebSocket server sent close frame');

                case 0x9: // Ping — respond with pong
                    $this->writeRaw($this->encodeFrame($frame['payload'], 0xA));
                    continue 2;

                case 0xA: // Pong — ignore
                    continue 2;

                default:
                    // Unknown opcode, skip
                    continue 2;
            }
        }
    }

    /**
     * Gracefully close the WebSocket connection.
     */
    public function close(): void
    {
        if ($this->connected && $this->socket !== null) {
            try {
                // Send close frame (opcode 0x8)
                $this->writeRaw($this->encodeFrame('', 0x8));
            } catch (\Throwable) {
                // Best effort
            }
            $this->connected = false;
        }

        $this->closeSocket();
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== null;
    }

    // ─── Handshake ───────────────────────────────────────────────────

    private function performHandshake(string $host, int $port, string $path): void
    {
        $key = base64_encode(random_bytes(16));

        $headers = [
            "GET {$path} HTTP/1.1",
            "Host: {$host}" . ($port !== 443 && $port !== 80 ? ":{$port}" : ''),
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: {$key}",
            "Sec-WebSocket-Version: 13",
        ];

        foreach ($this->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        $request = implode("\r\n", $headers) . "\r\n\r\n";
        $this->writeRaw($request);

        // Read the HTTP response
        $response = $this->readHttpResponse();

        // Verify 101 Switching Protocols
        if (!preg_match('/^HTTP\/1\.\d\s+101\s/i', $response)) {
            // Extract status for error message
            $firstLine = strtok($response, "\r\n");
            throw new ProviderException(
                "WebSocket handshake failed: {$firstLine}"
            );
        }

        // Verify Sec-WebSocket-Accept
        $expectedAccept = base64_encode(
            sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );

        if (!preg_match('/Sec-WebSocket-Accept:\s*(.+)\r?\n/i', $response, $matches)) {
            throw new ProviderException('WebSocket handshake: missing Sec-WebSocket-Accept header');
        }

        if (trim($matches[1]) !== $expectedAccept) {
            throw new ProviderException('WebSocket handshake: Sec-WebSocket-Accept mismatch');
        }
    }

    private function readHttpResponse(): string
    {
        $response = '';
        $headerEnd = false;

        while (!$headerEnd) {
            $byte = $this->readRaw(1);
            if ($byte === '') {
                throw new ProviderException('WebSocket handshake: connection closed during response');
            }
            $response .= $byte;

            // Check for end of headers (\r\n\r\n)
            if (str_ends_with($response, "\r\n\r\n")) {
                $headerEnd = true;
            }

            // Safety limit
            if (strlen($response) > 8192) {
                throw new ProviderException('WebSocket handshake: response headers too large');
            }
        }

        return $response;
    }

    // ─── Frame encoding (RFC 6455) ───────────────────────────────────

    /**
     * Encode a WebSocket frame. Client frames MUST be masked.
     */
    private function encodeFrame(string $payload, int $opcode): string
    {
        $length = strlen($payload);
        $frame = '';

        // FIN bit + opcode
        $frame .= chr(0x80 | $opcode);

        // Mask bit (1 for client) + payload length
        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } elseif ($length < 65536) {
            $frame .= chr(0x80 | 126);
            $frame .= pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127);
            $frame .= pack('J', $length);
        }

        // Masking key (4 random bytes)
        $mask = random_bytes(4);
        $frame .= $mask;

        // Masked payload
        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        return $frame;
    }

    /**
     * Read and decode a WebSocket frame from the socket.
     *
     * @return array{opcode: int, payload: string}|null
     */
    private function readFrame(): ?array
    {
        // Read first 2 bytes (FIN/opcode + mask/length)
        $header = $this->readRaw(2);
        if (strlen($header) < 2) {
            return null;
        }

        $byte1 = ord($header[0]);
        $byte2 = ord($header[1]);

        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 & 0x80) !== 0;
        $length = $byte2 & 0x7F;

        // Extended payload length
        if ($length === 126) {
            $extLen = $this->readRaw(2);
            if (strlen($extLen) < 2) {
                return null;
            }
            $length = unpack('n', $extLen)[1];
        } elseif ($length === 127) {
            $extLen = $this->readRaw(8);
            if (strlen($extLen) < 8) {
                return null;
            }
            $length = unpack('J', $extLen)[1];
        }

        // Masking key (server frames are typically unmasked)
        $maskKey = '';
        if ($masked) {
            $maskKey = $this->readRaw(4);
            if (strlen($maskKey) < 4) {
                return null;
            }
        }

        // Read payload
        $payload = '';
        if ($length > 0) {
            $payload = $this->readRaw($length);
            if (strlen($payload) < $length) {
                return null;
            }

            // Unmask if needed
            if ($masked) {
                for ($i = 0; $i < $length; $i++) {
                    $payload[$i] = $payload[$i] ^ $maskKey[$i % 4];
                }
            }
        }

        return ['opcode' => $opcode, 'payload' => $payload];
    }

    // ─── Raw I/O ─────────────────────────────────────────────────────

    private function writeRaw(string $data): void
    {
        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $result = @fwrite($this->socket, substr($data, $written));
            if ($result === false || $result === 0) {
                $this->connected = false;
                throw new ProviderException('WebSocket write failed');
            }
            $written += $result;
        }
    }

    private function readRaw(int $length): string
    {
        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = @fread($this->socket, $remaining);

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out'])) {
                    throw new ProviderException(
                        "WebSocket read timeout after {$this->timeout}s"
                    );
                }
                if (!empty($meta['eof'])) {
                    $this->connected = false;
                    throw new ProviderException('WebSocket connection closed by peer');
                }
                // Empty read but no timeout/eof — break to avoid infinite loop
                break;
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    private function assertConnected(): void
    {
        if (!$this->connected || $this->socket === null) {
            throw new ProviderException('WebSocket is not connected');
        }
    }

    private function closeSocket(): void
    {
        if ($this->socket !== null && is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }
}
