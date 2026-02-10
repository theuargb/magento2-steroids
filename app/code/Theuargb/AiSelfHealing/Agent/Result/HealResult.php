<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Result;

/**
 * Structured result from the healer agent.
 */
class HealResult
{
    public function __construct(
        private readonly bool $isHealed,
        private readonly string $summary,
        private readonly string $reasoningLog,
        private readonly array $actionsTaken,
        private readonly int $toolCallsCount,
        private readonly string $modelUsed,
        private readonly int $tokensUsed,
        private readonly ?string $diff,
        private readonly ?string $failureReason,
        private readonly float $durationSeconds
    ) {}

    public function isHealed(): bool
    {
        return $this->isHealed;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getReasoningLog(): string
    {
        return $this->reasoningLog;
    }

    public function getActionsTaken(): array
    {
        return $this->actionsTaken;
    }

    public function getToolCallsCount(): int
    {
        return $this->toolCallsCount;
    }

    public function getModelUsed(): string
    {
        return $this->modelUsed;
    }

    public function getTokensUsed(): int
    {
        return $this->tokensUsed;
    }

    public function getDiff(): ?string
    {
        return $this->diff;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getDurationSeconds(): float
    {
        return $this->durationSeconds;
    }
}
