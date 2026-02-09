<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Cron;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Theuargb\AiSelfHealing\Helper\Config;

class CaptureHomepageSnapshot
{
    private Config $config;
    private Curl $curl;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        Curl $curl,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            
            $this->curl->setTimeout(10);
            $this->curl->get($baseUrl);
            
            $statusCode = $this->curl->getStatus();
            $body = $this->curl->getBody();
            
            $snapshot = [
                'url' => $baseUrl,
                'status_code' => $statusCode,
                'body_length' => strlen($body),
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            // Send to agent for storage
            $endpoint = $this->config->getAgentEndpoint();
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post($endpoint . '/snapshot', json_encode($snapshot));
            
            $this->logger->info('Homepage snapshot captured successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to capture homepage snapshot: ' . $e->getMessage());
        }
    }
}
