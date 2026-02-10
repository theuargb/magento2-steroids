<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Agent\Provider\OpenAIRealtime;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolCallResultMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\HandleWithTools;
use NeuronAI\Providers\MessageMapperInterface;
use NeuronAI\Providers\ToolPayloadMapperInterface;
use NeuronAI\Tools\ToolInterface;
use Generator;

/**
 * OpenAI Realtime API provider using WebSocket transport.
 *
 * Connects to wss://api.openai.com/v1/realtime for lower-latency text inference.
 *
 * Key benefits over standard Chat Completions:
 * - Persistent connection eliminates per-request TLS/HTTP overhead
 * - Server-Sent Events style streaming over a single socket
 * - Supports function calling via the Realtime protocol
 * - Session-level configuration (instructions, tools) set once
 *
 * Uses a built-in native PHP WebSocket client (stream_socket_client + OpenSSL).
 * No external WebSocket dependencies required.
 */
class OpenAIRealtimeProvider implements AIProviderInterface
{
    use HandleWithTools;

    protected ?string $system = null;
    protected MessageMapperInterface $messageMapper;
    protected ToolPayloadMapperInterface $toolPayloadMapper;

    /**
     * WebSocket connection instance.
     */
    private ?NativeWebSocketClient $ws = null;

    /**
     * Whether the session has been configured on the server.
     */
    private bool $sessionConfigured = false;

    /**
     * Timeout for reading from WebSocket (seconds).
     */
    private int $readTimeout;

    /**
     * @param string $key OpenAI API key
     * @param string $model Realtime model name (e.g. gpt-4o-realtime-preview, gpt-realtime)
     * @param int $readTimeout WebSocket read timeout in seconds
     * @param array<string, mixed> $parameters Additional session parameters
     */
    public function __construct(
        protected string $key,
        protected string $model = 'gpt-4o-realtime-preview',
        int $readTimeout = 30,
        protected array $parameters = [],
    ) {
        $this->readTimeout = $readTimeout;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->system = $prompt;
        return $this;
    }

    public function messageMapper(): MessageMapperInterface
    {
        return $this->messageMapper ?? $this->messageMapper = new RealtimeMessageMapper();
    }

    public function toolPayloadMapper(): ToolPayloadMapperInterface
    {
        return $this->toolPayloadMapper ?? $this->toolPayloadMapper = new RealtimeToolPayloadMapper();
    }

    /**
     * Not used for WebSocket transport — provided for interface compatibility.
     */
    public function setClient(Client $client): AIProviderInterface
    {
        return $this;
    }

    // ─── Connection Management ───────────────────────────────────────

    protected function connect(): void
    {
        if ($this->ws !== null && $this->ws->isConnected()) {
            return;
        }

        $url = "wss://api.openai.com/v1/realtime?model={$this->model}";

        $this->ws = new NativeWebSocketClient(
            url: $url,
            headers: [
                'Authorization' => 'Bearer ' . $this->key,
                'OpenAI-Beta' => 'realtime=v1',
            ],
            timeout: $this->readTimeout,
        );

        $this->ws->connect();

        // Wait for session.created
        $created = $this->receiveEvent();
        if (($created['type'] ?? '') !== 'session.created') {
            throw new ProviderException(
                'Expected session.created event, got: ' . ($created['type'] ?? 'unknown')
            );
        }

        $this->sessionConfigured = false;
    }

    protected function disconnect(): void
    {
        if ($this->ws !== null) {
            try {
                $this->ws->close();
            } catch (\Throwable) {
                // Ignore close errors
            }
            $this->ws = null;
            $this->sessionConfigured = false;
        }
    }

    /**
     * Configure the session with instructions and tools.
     */
    protected function configureSession(): void
    {
        if ($this->sessionConfigured) {
            return;
        }

        $session = [
            'modalities' => ['text'],
            'instructions' => $this->system ?? '',
        ];

        if (!empty($this->tools)) {
            $session['tools'] = $this->toolPayloadMapper()->map($this->tools);
            $session['tool_choice'] = 'auto';
        }

        $session = array_merge($session, $this->parameters);

        $this->sendEvent([
            'type' => 'session.update',
            'session' => $session,
        ]);

        // Wait for session.updated confirmation
        $updated = $this->receiveEvent();
        if (($updated['type'] ?? '') !== 'session.updated') {
            throw new ProviderException(
                'Expected session.updated event, got: ' . ($updated['type'] ?? 'unknown')
            );
        }

        $this->sessionConfigured = true;
    }

    // ─── Event I/O ───────────────────────────────────────────────────

    protected function sendEvent(array $event): void
    {
        $this->ws->send(json_encode($event, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws ProviderException
     */
    protected function receiveEvent(): array
    {
        try {
            $raw = $this->ws->receive();
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (ProviderException $e) {
            // Re-throw ProviderException as-is (includes timeout errors)
            throw $e;
        } catch (\Throwable $e) {
            throw new ProviderException('OpenAI Realtime WebSocket error: ' . $e->getMessage());
        }
    }

    // ─── Chat (synchronous) ─────────────────────────────────────────

    public function chat(array $messages): Message
    {
        return $this->chatAsync($messages)->wait();
    }

    public function chatAsync(array $messages): PromiseInterface
    {
        try {
            $this->connect();
            $this->configureSession();

            // Send conversation items for all messages
            $mapped = $this->messageMapper()->map($messages);
            foreach ($mapped as $item) {
                $this->sendEvent([
                    'type' => 'conversation.item.create',
                    'item' => $item,
                ]);
            }

            // Request a response (text-only for speed)
            $this->sendEvent([
                'type' => 'response.create',
                'response' => [
                    'modalities' => ['text'],
                ],
            ]);

            // Collect the response
            $result = $this->collectResponse();

            return Create::promiseFor($result);
        } catch (ProviderException $e) {
            $this->disconnect();
            return Create::rejectionFor($e);
        } catch (\Throwable $e) {
            $this->disconnect();
            return Create::rejectionFor(
                new ProviderException('OpenAI Realtime error: ' . $e->getMessage(), 0, $e)
            );
        }
    }

    /**
     * Listen for server events until response.done, assembling the final message.
     */
    protected function collectResponse(): Message
    {
        $text = '';
        $toolCalls = [];
        $inputTokens = 0;
        $outputTokens = 0;
        $finishReason = '';

        while (true) {
            $event = $this->receiveEvent();
            $type = $event['type'] ?? '';

            switch ($type) {
                case 'response.text.delta':
                case 'response.output_text.delta':
                    $text .= $event['delta'] ?? '';
                    break;

                case 'response.function_call_arguments.delta':
                    // Accumulate function call arguments
                    $itemId = $event['item_id'] ?? '';
                    $callId = $event['call_id'] ?? '';
                    if (!isset($toolCalls[$callId])) {
                        $toolCalls[$callId] = [
                            'id' => $callId,
                            'item_id' => $itemId,
                            'name' => '',
                            'arguments' => '',
                        ];
                    }
                    $toolCalls[$callId]['arguments'] .= $event['delta'] ?? '';
                    break;

                case 'response.function_call_arguments.done':
                    $callId = $event['call_id'] ?? '';
                    if (isset($toolCalls[$callId])) {
                        $toolCalls[$callId]['arguments'] = $event['arguments'] ?? $toolCalls[$callId]['arguments'];
                        $toolCalls[$callId]['name'] = $event['name'] ?? $toolCalls[$callId]['name'];
                    }
                    break;

                case 'response.output_item.done':
                    // Capture function call name from the completed item
                    $item = $event['item'] ?? [];
                    if (($item['type'] ?? '') === 'function_call') {
                        $callId = $item['call_id'] ?? '';
                        if (!isset($toolCalls[$callId])) {
                            $toolCalls[$callId] = [
                                'id' => $callId,
                                'item_id' => $item['id'] ?? '',
                                'name' => $item['name'] ?? '',
                                'arguments' => $item['arguments'] ?? '',
                            ];
                        } else {
                            $toolCalls[$callId]['name'] = $item['name'] ?? $toolCalls[$callId]['name'];
                            if (!empty($item['arguments'])) {
                                $toolCalls[$callId]['arguments'] = $item['arguments'];
                            }
                        }
                    }
                    break;

                case 'response.done':
                    $response = $event['response'] ?? [];
                    $finishReason = $response['status'] ?? 'completed';

                    // Extract usage
                    $usage = $response['usage'] ?? [];
                    $inputTokens = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0;
                    $outputTokens = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0;

                    // Extract tool calls from response output items
                    foreach (($response['output'] ?? []) as $outputItem) {
                        if (($outputItem['type'] ?? '') === 'function_call') {
                            $callId = $outputItem['call_id'] ?? '';
                            if (!isset($toolCalls[$callId]) && !empty($callId)) {
                                $toolCalls[$callId] = [
                                    'id' => $callId,
                                    'item_id' => $outputItem['id'] ?? '',
                                    'name' => $outputItem['name'] ?? '',
                                    'arguments' => $outputItem['arguments'] ?? '',
                                ];
                            }
                        }
                        // Extract text from message output items
                        if (($outputItem['type'] ?? '') === 'message') {
                            foreach (($outputItem['content'] ?? []) as $content) {
                                if (($content['type'] ?? '') === 'text') {
                                    $text = $content['text'] ?? $text;
                                }
                            }
                        }
                    }

                    goto done;

                case 'error':
                    $error = $event['error'] ?? [];
                    throw new ProviderException(
                        'OpenAI Realtime error: ' . ($error['message'] ?? json_encode($event))
                    );

                // Ignore other lifecycle events
                default:
                    break;
            }
        }

        done:

        // Build the appropriate message type
        if (!empty($toolCalls)) {
            return $this->createToolCallMessage($toolCalls, $text, $inputTokens, $outputTokens);
        }

        $message = new AssistantMessage($text);
        $message->setStopReason($finishReason);
        $message->setUsage(new Usage($inputTokens, $outputTokens));

        return $message;
    }

    /**
     * Create a ToolCallMessage from Realtime API function calls.
     */
    protected function createToolCallMessage(
        array $toolCalls,
        string $text,
        int $inputTokens,
        int $outputTokens
    ): ToolCallMessage {
        $tools = [];

        foreach ($toolCalls as $call) {
            $tool = $this->findTool($call['name']);
            $arguments = json_decode($call['arguments'] ?: '{}', true) ?? [];
            $tool->setInputs($arguments)->setCallId($call['id']);
            $tools[] = $tool;
        }

        $message = new ToolCallMessage($text, $tools);
        $message->setStopReason('tool_calls');
        $message->setUsage(new Usage($inputTokens, $outputTokens));

        // Store metadata for the Realtime protocol tool result flow
        $message->addMetadata('tool_calls', array_map(fn(array $call) => [
            'id' => $call['id'],
            'type' => 'function',
            'function' => [
                'name' => $call['name'],
                'arguments' => $call['arguments'],
            ],
        ], $toolCalls));

        return $message;
    }

    /**
     * Submit tool results back to the Realtime session and get the next response.
     */
    public function submitToolResults(ToolCallResultMessage $toolResult): Message
    {
        $this->connect();

        foreach ($toolResult->getTools() as $tool) {
            $this->sendEvent([
                'type' => 'conversation.item.create',
                'item' => [
                    'type' => 'function_call_output',
                    'call_id' => $tool->getCallId(),
                    'output' => is_string($tool->getResult())
                        ? $tool->getResult()
                        : json_encode($tool->getResult()),
                ],
            ]);
        }

        // Request the model to continue with tool results
        $this->sendEvent([
            'type' => 'response.create',
            'response' => [
                'modalities' => ['text'],
            ],
        ]);

        return $this->collectResponse();
    }

    // ─── Streaming ──────────────────────────────────────────────────

    /**
     * @throws ProviderException
     */
    public function stream(array|string $messages, callable $executeToolsCallback): Generator
    {
        $this->connect();
        $this->configureSession();

        if (is_string($messages)) {
            $messages = [new UserMessage($messages)];
        }

        // Send conversation items
        $mapped = $this->messageMapper()->map($messages);
        foreach ($mapped as $item) {
            $this->sendEvent([
                'type' => 'conversation.item.create',
                'item' => $item,
            ]);
        }

        // Request response
        $this->sendEvent([
            'type' => 'response.create',
            'response' => [
                'modalities' => ['text'],
            ],
        ]);

        // Stream events
        $text = '';
        $toolCalls = [];

        while (true) {
            $event = $this->receiveEvent();
            $type = $event['type'] ?? '';

            switch ($type) {
                case 'response.text.delta':
                case 'response.output_text.delta':
                    $delta = $event['delta'] ?? '';
                    $text .= $delta;
                    yield $delta;
                    break;

                case 'response.function_call_arguments.delta':
                    $callId = $event['call_id'] ?? '';
                    if (!isset($toolCalls[$callId])) {
                        $toolCalls[$callId] = [
                            'id' => $callId,
                            'name' => '',
                            'arguments' => '',
                        ];
                    }
                    $toolCalls[$callId]['arguments'] .= $event['delta'] ?? '';
                    break;

                case 'response.function_call_arguments.done':
                    $callId = $event['call_id'] ?? '';
                    if (isset($toolCalls[$callId])) {
                        $toolCalls[$callId]['name'] = $event['name'] ?? $toolCalls[$callId]['name'];
                        $toolCalls[$callId]['arguments'] = $event['arguments'] ?? $toolCalls[$callId]['arguments'];
                    }
                    break;

                case 'response.output_item.done':
                    $item = $event['item'] ?? [];
                    if (($item['type'] ?? '') === 'function_call') {
                        $callId = $item['call_id'] ?? '';
                        $toolCalls[$callId] = [
                            'id' => $callId,
                            'name' => $item['name'] ?? '',
                            'arguments' => $item['arguments'] ?? '',
                        ];
                    }
                    break;

                case 'response.done':
                    $response = $event['response'] ?? [];

                    // Yield usage info
                    $usage = $response['usage'] ?? [];
                    if (!empty($usage)) {
                        yield json_encode(['usage' => [
                            'input_tokens' => $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0,
                            'output_tokens' => $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0,
                        ]]);
                    }

                    // Handle tool calls
                    if (!empty($toolCalls)) {
                        yield from $executeToolsCallback(
                            $this->createToolCallMessage($toolCalls, $text, 0, 0)
                        );
                        return;
                    }

                    return;

                case 'error':
                    $error = $event['error'] ?? [];
                    throw new ProviderException(
                        'OpenAI Realtime stream error: ' . ($error['message'] ?? json_encode($event))
                    );

                default:
                    break;
            }
        }
    }

    // ─── Structured Output ──────────────────────────────────────────

    public function structured(array $messages, string $class, array $response_schema): Message
    {
        // Realtime API doesn't natively support json_schema response_format,
        // so we inject schema instructions into the prompt and parse.
        $tk = explode('\\', $class);
        $className = end($tk);

        $schemaJson = json_encode($response_schema, JSON_PRETTY_PRINT);
        $structuredInstruction = "\n\nYou MUST respond with valid JSON matching this schema exactly:\n"
            . "Schema name: {$className}\n"
            . "Schema: {$schemaJson}\n"
            . "Return ONLY the JSON object, no markdown fences or extra text.";

        // Temporarily append to system prompt
        $originalSystem = $this->system;
        $this->system = ($this->system ?? '') . $structuredInstruction;
        $this->sessionConfigured = false; // Force reconfigure

        try {
            $result = $this->chat($messages);
        } finally {
            $this->system = $originalSystem;
            $this->sessionConfigured = false;
        }

        return $result;
    }
}
