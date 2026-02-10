<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\App\CacheInterface;
use Theuargb\AiSelfHealing\Helper\Config;

/**
 * Prevents stampede: limits how many concurrent healing requests
 * can be in-flight at once using cache-based counting.
 */
class ConcurrencyGuard
{
    private const CACHE_KEY = 'aiselfhealing_concurrency_count';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Config $config
    ) {}

    /**
     * Try to acquire a concurrency slot. Returns true if allowed.
     */
    public function acquire(): bool
    {
        $max = $this->config->getMaxConcurrentHealings();
        if ($max <= 0) {
            $max = 2;
        }

        $current = (int) $this->cache->load(self::CACHE_KEY);
        if ($current >= $max) {
            return false;
        }

        $this->cache->save(
            (string) ($current + 1),
            self::CACHE_KEY,
            ['aiselfhealing_concurrency'],
            300 // 5 min TTL as safety net
        );
        return true;
    }

    /**
     * Release a concurrency slot.
     */
    public function release(): void
    {
        $current = (int) $this->cache->load(self::CACHE_KEY);
        $this->cache->save(
            (string) max(0, $current - 1),
            self::CACHE_KEY,
            ['aiselfhealing_concurrency'],
            300
        );
    }
}
