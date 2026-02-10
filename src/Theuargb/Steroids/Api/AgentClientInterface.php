<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Api;

use Theuargb\Steroids\Agent\Result\HealResult;
use Theuargb\Steroids\Agent\Result\FallbackResult;

interface AgentClientInterface
{
    /**
     * Run AI healing agent in-process.
     *
     * @param array $context Exception context payload
     * @param int $timeout Timeout in seconds
     * @return HealResult
     */
    public function requestHealing(array $context, int $timeout): HealResult;

    /**
     * Generate fallback HTML when healing fails.
     *
     * @param array $context Exception context payload
     * @param array $designContext Free-form design JSON from admin config
     * @param string|null $fallbackPrompt Admin instructions for fallback agent
     * @param int $timeout Timeout in seconds
     * @return FallbackResult
     */
    public function requestFallbackHtml(
        array $context,
        array $designContext,
        ?string $fallbackPrompt,
        int $timeout
    ): FallbackResult;

    /**
     * Check if agent is available (LLM API key configured).
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
