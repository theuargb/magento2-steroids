<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\Model\AbstractModel;
use Theuargb\AiSelfHealing\Api\Data\HealingAttemptInterface;

class HealingAttempt extends AbstractModel implements HealingAttemptInterface
{
    protected function _construct()
    {
        $this->_init(ResourceModel\HealingAttempt::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);
        return $value !== null ? (int) $value : null;
    }

    public function setEntityId(int $entityId): HealingAttemptInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getFingerprint(): ?string
    {
        return $this->getData(self::FINGERPRINT);
    }

    public function setFingerprint(string $fingerprint): HealingAttemptInterface
    {
        return $this->setData(self::FINGERPRINT, $fingerprint);
    }

    public function getUrl(): ?string
    {
        return $this->getData(self::URL);
    }

    public function setUrl(string $url): HealingAttemptInterface
    {
        return $this->setData(self::URL, $url);
    }

    public function getErrorMessage(): ?string
    {
        return $this->getData(self::ERROR_MESSAGE);
    }

    public function setErrorMessage(string $errorMessage): HealingAttemptInterface
    {
        return $this->setData(self::ERROR_MESSAGE, $errorMessage);
    }

    public function getErrorTrace(): ?string
    {
        return $this->getData(self::ERROR_TRACE);
    }

    public function setErrorTrace(string $errorTrace): HealingAttemptInterface
    {
        return $this->setData(self::ERROR_TRACE, $errorTrace);
    }

    public function getContextSnapshot(): ?string
    {
        return $this->getData(self::CONTEXT_SNAPSHOT);
    }

    public function setContextSnapshot(string $contextSnapshot): HealingAttemptInterface
    {
        return $this->setData(self::CONTEXT_SNAPSHOT, $contextSnapshot);
    }

    public function getAgentRequest(): ?string
    {
        return $this->getData(self::AGENT_REQUEST);
    }

    public function setAgentRequest(string $agentRequest): HealingAttemptInterface
    {
        return $this->setData(self::AGENT_REQUEST, $agentRequest);
    }

    public function getAgentResponse(): ?string
    {
        return $this->getData(self::AGENT_RESPONSE);
    }

    public function setAgentResponse(string $agentResponse): HealingAttemptInterface
    {
        return $this->setData(self::AGENT_RESPONSE, $agentResponse);
    }

    public function getStatus(): ?string
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus(string $status): HealingAttemptInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getOutcome(): ?string
    {
        return $this->getData(self::OUTCOME);
    }

    public function setOutcome(string $outcome): HealingAttemptInterface
    {
        return $this->setData(self::OUTCOME, $outcome);
    }

    public function getExecutionTimeMs(): ?int
    {
        $value = $this->getData(self::EXECUTION_TIME_MS);
        return $value !== null ? (int) $value : null;
    }

    public function setExecutionTimeMs(int $executionTimeMs): HealingAttemptInterface
    {
        return $this->setData(self::EXECUTION_TIME_MS, $executionTimeMs);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): HealingAttemptInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): HealingAttemptInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
