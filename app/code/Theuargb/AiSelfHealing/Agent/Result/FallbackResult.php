<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Result;

/**
 * Structured result from the fallback HTML generation agent.
 */
class FallbackResult
{
    public function __construct(
        private readonly bool $hasHtml,
        private readonly string $html
    ) {}

    public function hasHtml(): bool
    {
        return $this->hasHtml;
    }

    public function getHtml(): string
    {
        return $this->html;
    }
}
