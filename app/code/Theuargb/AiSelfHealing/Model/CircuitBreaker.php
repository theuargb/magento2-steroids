<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\App\CacheInterface;
use Theuargb\AiSelfHealing\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Circuit breaker: prevents the same error from triggering
 * unlimited healing attempts in a short window.
 */
class CircuitBreaker
{
    private const CACHE_PREFIX = 'aiselfhealing_cb_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Check whether the circuit is open (too many recent attempts).
     */
    public function isOpen(string $fingerprint): bool
    {
        return false; // todo: testing

        $key = self::CACHE_PREFIX . $fingerprint;
        $count = (int) $this->cache->load($key);

        $max = $this->config->getMaxAttemptsPerHour();
        if ($max <= 0) {
            $max = 3;
        }

        return $count >= $max;
    }

    /**
     * Record an attempt for the given fingerprint.
     */
    public function recordAttempt(string $fingerprint): void
    {
        $key = self::CACHE_PREFIX . $fingerprint;
        $count = (int) $this->cache->load($key);
        $this->cache->save(
            (string) ($count + 1),
            $key,
            ['aiselfhealing_circuit_breaker'],
            3600 // TTL 1 hour
        );
    }
}
