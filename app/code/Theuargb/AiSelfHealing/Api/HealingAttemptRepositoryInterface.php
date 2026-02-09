<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Api;

use Theuargb\AiSelfHealing\Api\Data\HealingAttemptInterface;

interface HealingAttemptRepositoryInterface
{
    /**
     * Save healing attempt
     *
     * @param HealingAttemptInterface $healingAttempt
     * @return HealingAttemptInterface
     */
    public function save(HealingAttemptInterface $healingAttempt): HealingAttemptInterface;

    /**
     * Get healing attempt by ID
     *
     * @param int $entityId
     * @return HealingAttemptInterface
     */
    public function getById(int $entityId): HealingAttemptInterface;

    /**
     * Delete healing attempt
     *
     * @param HealingAttemptInterface $healingAttempt
     * @return bool
     */
    public function delete(HealingAttemptInterface $healingAttempt): bool;

    /**
     * Count recent attempts by fingerprint
     *
     * @param string $fingerprint
     * @param int $withinHours
     * @return int
     */
    public function countRecentAttemptsByFingerprint(string $fingerprint, int $withinHours = 1): int;
}
