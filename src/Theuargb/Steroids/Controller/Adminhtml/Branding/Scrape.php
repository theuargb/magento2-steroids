<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Controller\Adminhtml\Branding;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;
use Theuargb\Steroids\Helper\Config;

/**
 * AJAX controller: calls Firecrawl /v2/scrape with formats=["branding"]
 * and returns the extracted branding JSON to the admin UI.
 */
class Scrape extends Action
{
    public const ADMIN_RESOURCE = 'Theuargb_Steroids::config';

    private const FIRECRAWL_ENDPOINT = 'https://api.firecrawl.dev/v2/scrape';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $useSavedKey = (bool) $this->getRequest()->getParam('use_saved_key', false);
            $storeUrl    = (string) $this->getRequest()->getParam('store_url', '');

            if ($useSavedKey) {
                // Read the already-saved & encrypted key from config
                $apiKey = $this->config->getFirecrawlApiKey();
            } else {
                $apiKey = (string) $this->getRequest()->getParam('firecrawl_api_key', '');
            }

            if (empty($apiKey)) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Firecrawl API Key is required. Please enter a key and save the configuration first.',
                ]);
            }

            if (empty($storeUrl)) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Store URL is required.',
                ]);
            }

            $payload = $this->json->serialize([
                'url'             => $storeUrl,
                'onlyMainContent' => false,
                'maxAge'          => 172800000,
                'parsers'         => [],
                'formats'         => ['branding'],
            ]);

            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->setTimeout(30);
            $this->curl->post(self::FIRECRAWL_ENDPOINT, $payload);

            $httpStatus = $this->curl->getStatus();
            $body       = $this->curl->getBody();

            $this->logger->info('[Steroids] Firecrawl scrape response', [
                'http_status' => $httpStatus,
                'url'         => $storeUrl,
                'body_length' => strlen($body),
            ]);

            if ($httpStatus < 200 || $httpStatus >= 300) {
                return $result->setData([
                    'success' => false,
                    'message' => sprintf(
                        'Firecrawl API returned HTTP %d. %s',
                        $httpStatus,
                        $this->extractErrorMessage($body)
                    ),
                ]);
            }

            $response = $this->json->unserialize($body);

            if (!is_array($response) || empty($response['success'])) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Firecrawl API returned an unsuccessful response: '
                        . ($response['error'] ?? 'unknown error'),
                ]);
            }

            // Extract branding from the response
            $branding = $response['data']['branding'] ?? null;

            if (empty($branding) || !is_array($branding)) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Firecrawl response did not contain branding data. '
                        . 'Make sure the URL is accessible and returns a webpage.',
                ]);
            }

            return $result->setData([
                'success'  => true,
                'branding' => $branding,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Steroids] Firecrawl scrape error: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract a human-readable error message from a Firecrawl error response body.
     */
    private function extractErrorMessage(string $body): string
    {
        try {
            $parsed = $this->json->unserialize($body);
            return $parsed['error'] ?? $parsed['message'] ?? '';
        } catch (\Exception $e) {
            return mb_substr($body, 0, 200);
        }
    }
}
