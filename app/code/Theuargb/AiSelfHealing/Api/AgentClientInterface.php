<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Api;

interface AgentClientInterface
{
    /**
     * Send healing request to AI agent
     *
     * @param array $context
     * @return array
     */
    public function heal(array $context): array;

    /**
     * Check if agent is available
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
