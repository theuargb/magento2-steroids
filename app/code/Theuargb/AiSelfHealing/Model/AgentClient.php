<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Theuargb\AiSelfHealing\Api\AgentClientInterface;
use Theuargb\AiSelfHealing\Helper\Config;

class AgentClient implements AgentClientInterface
{
    private Curl $curl;
    private Json $json;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        Curl $curl,
        Json $json,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->curl = $curl;
        $this->json = $json;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function heal(array $context): array
    {
        $endpoint = $this->config->getAgentEndpoint();
        $timeout = $this->config->getHealTimeout();

        try {
            $this->curl->setTimeout($timeout);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->addHeader('Content-Type', 'application/json');

            $payload = [
                'context' => $context,
                'max_tool_calls' => $this->config->getMaxToolCalls(),
                'readonly_mode' => $this->config->isReadonlyMode(),
                'disallowed_tools' => $this->config->getDisallowedToolActions(),
            ];

            $this->curl->post($endpoint . '/heal', $this->json->serialize($payload));
            
            $statusCode = $this->curl->getStatus();
            $response = $this->curl->getBody();

            if ($statusCode !== 200) {
                throw new \RuntimeException("Agent returned status code: {$statusCode}");
            }

            return $this->json->unserialize($response);
        } catch (\Exception $e) {
            $this->logger->error('Agent client error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function isAvailable(): bool
    {
        $endpoint = $this->config->getAgentEndpoint();
        
        try {
            $this->curl->setTimeout(2);
            $this->curl->get($endpoint . '/health');
            return $this->curl->getStatus() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
