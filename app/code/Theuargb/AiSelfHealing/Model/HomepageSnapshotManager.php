<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Model\StoreManagerInterface;
use Theuargb\AiSelfHealing\Api\Data\HomepageSnapshotInterface;
use Theuargb\AiSelfHealing\Model\HomepageSnapshotFactory;
use Theuargb\AiSelfHealing\Model\ResourceModel\HomepageSnapshot as SnapshotResource;
use Theuargb\AiSelfHealing\Model\ResourceModel\HomepageSnapshot\CollectionFactory as SnapshotCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Manages homepage snapshots — capturing and retrieval.
 */
class HomepageSnapshotManager
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly HomepageSnapshotFactory $snapshotFactory,
        private readonly SnapshotResource $snapshotResource,
        private readonly SnapshotCollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Capture a fresh snapshot of the homepage for the current store.
     */
    public function capture(): ?HomepageSnapshotInterface
    {
        try {
            $store = $this->storeManager->getStore();
            $baseUrl = $store->getBaseUrl();
            $storeId = (int) $store->getId();

            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_TIMEOUT, 15);
            $curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $curl->addHeader('User-Agent', 'AiSelfHealing-SnapshotBot/1.0');
            $curl->get($baseUrl);

            $statusCode = $curl->getStatus();
            $body = $curl->getBody();

            if ($statusCode !== 200 || empty($body)) {
                $this->logger->warning(
                    '[AiSelfHealing] Homepage snapshot capture got HTTP ' . $statusCode
                );
                return null;
            }

            // Extract inline CSS from the HTML
            $css = $this->extractCss($body);

            $snapshot = $this->snapshotFactory->create();
            $snapshot->setStoreId($storeId);
            $snapshot->setBaseUrl($baseUrl);
            $snapshot->setFullHtml($body);
            $snapshot->setInlinedCss($css);
            $snapshot->setHttpStatusCode($statusCode);

            $this->snapshotResource->save($snapshot);

            $this->logger->info('[AiSelfHealing] Homepage snapshot captured for store ' . $storeId);
            return $snapshot;
        } catch (\Throwable $e) {
            $this->logger->error('[AiSelfHealing] Snapshot capture failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the latest snapshot for the current store.
     */
    public function getLatestSnapshot(): ?HomepageSnapshotInterface
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('store_id', $storeId);
            $collection->setOrder('captured_at', 'DESC');
            $collection->setPageSize(1);

            $snapshot = $collection->getFirstItem();
            return $snapshot->getEntityId() ? $snapshot : null;
        } catch (\Throwable $e) {
            $this->logger->error('[AiSelfHealing] Failed to retrieve snapshot: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract <style> blocks and linked CSS from HTML.
     * Fetches external stylesheet contents and combines them with inline styles.
     */
    private function extractCss(string $html): string
    {
        $css = '';

        // 1. Extract inline <style> blocks
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $html, $matches)) {
            $css .= implode("\n", $matches[1]);
        }

        // 2. Extract and fetch linked stylesheets
        if (preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $linkMatches)) {
            foreach ($linkMatches[1] as $href) {
                try {
                    $resolvedUrl = $this->resolveUrl($href);
                    $stylesheetCurl = $this->curlFactory->create();
                    $stylesheetCurl->setOption(CURLOPT_TIMEOUT, 5);
                    $stylesheetCurl->setOption(CURLOPT_FOLLOWLOCATION, true);
                    $stylesheetCurl->addHeader('User-Agent', 'AiSelfHealing-SnapshotBot/1.0');
                    $stylesheetCurl->get($resolvedUrl);

                    if ($stylesheetCurl->getStatus() === 200) {
                        $css .= "\n/* Source: {$href} */\n" . $stylesheetCurl->getBody();
                    }
                } catch (\Throwable $e) {
                    $this->logger->debug(
                        '[AiSelfHealing] Failed to fetch stylesheet: ' . $href . ' - ' . $e->getMessage()
                    );
                }
            }
        }

        // Also try the alternate link format: href before rel
        if (preg_match_all('/<link[^>]+href=["\']([^"\']+)["\'][^>]*rel=["\']stylesheet["\'][^>]*>/i', $html, $linkMatches2)) {
            foreach ($linkMatches2[1] as $href) {
                // Skip if already fetched
                if (isset($linkMatches[1]) && in_array($href, $linkMatches[1], true)) {
                    continue;
                }
                try {
                    $resolvedUrl = $this->resolveUrl($href);
                    $stylesheetCurl = $this->curlFactory->create();
                    $stylesheetCurl->setOption(CURLOPT_TIMEOUT, 5);
                    $stylesheetCurl->setOption(CURLOPT_FOLLOWLOCATION, true);
                    $stylesheetCurl->addHeader('User-Agent', 'AiSelfHealing-SnapshotBot/1.0');
                    $stylesheetCurl->get($resolvedUrl);

                    if ($stylesheetCurl->getStatus() === 200) {
                        $css .= "\n/* Source: {$href} */\n" . $stylesheetCurl->getBody();
                    }
                } catch (\Throwable $e) {
                    $this->logger->debug(
                        '[AiSelfHealing] Failed to fetch stylesheet: ' . $href . ' - ' . $e->getMessage()
                    );
                }
            }
        }

        return $css;
    }

    /**
     * Resolve a potentially relative URL to an absolute one.
     */
    private function resolveUrl(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        try {
            $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
            $href = ltrim($href, '/');
            return $baseUrl . '/' . $href;
        } catch (\Throwable $e) {
            return $href;
        }
    }
}
