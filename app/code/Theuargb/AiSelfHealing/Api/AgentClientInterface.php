<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Api;

use Theuargb\AiSelfHealing\Agent\Result\HealResult;
use Theuargb\AiSelfHealing\Agent\Result\FallbackResult;

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
     * @param string $homepageHtml Full homepage HTML for design reference
     * @param string $homepageCss Inlined CSS for styling
     * @param int $timeout Timeout in seconds
     * @return FallbackResult
     */
    public function requestFallbackHtml(
        array $context,
        string $homepageHtml,
        string $homepageCss,
        int $timeout
    ): FallbackResult;

    /**
     * Check if agent is available (LLM API key configured).
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
