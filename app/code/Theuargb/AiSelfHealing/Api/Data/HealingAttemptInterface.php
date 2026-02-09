<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Api\Data;

interface HealingAttemptInterface
{
    public const ENTITY_ID = 'entity_id';
    public const FINGERPRINT = 'fingerprint';
    public const URL = 'url';
    public const ERROR_MESSAGE = 'error_message';
    public const ERROR_TRACE = 'error_trace';
    public const CONTEXT_SNAPSHOT = 'context_snapshot';
    public const AGENT_REQUEST = 'agent_request';
    public const AGENT_RESPONSE = 'agent_response';
    public const STATUS = 'status';
    public const OUTCOME = 'outcome';
    public const EXECUTION_TIME_MS = 'execution_time_ms';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get entity ID
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Set entity ID
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId(int $entityId): self;

    /**
     * Get fingerprint
     *
     * @return string|null
     */
    public function getFingerprint(): ?string;

    /**
     * Set fingerprint
     *
     * @param string $fingerprint
     * @return $this
     */
    public function setFingerprint(string $fingerprint): self;

    /**
     * Get URL
     *
     * @return string|null
     */
    public function getUrl(): ?string;

    /**
     * Set URL
     *
     * @param string $url
     * @return $this
     */
    public function setUrl(string $url): self;

    /**
     * Get error message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string;

    /**
     * Set error message
     *
     * @param string $errorMessage
     * @return $this
     */
    public function setErrorMessage(string $errorMessage): self;

    /**
     * Get error trace
     *
     * @return string|null
     */
    public function getErrorTrace(): ?string;

    /**
     * Set error trace
     *
     * @param string $errorTrace
     * @return $this
     */
    public function setErrorTrace(string $errorTrace): self;

    /**
     * Get context snapshot
     *
     * @return string|null
     */
    public function getContextSnapshot(): ?string;

    /**
     * Set context snapshot
     *
     * @param string $contextSnapshot
     * @return $this
     */
    public function setContextSnapshot(string $contextSnapshot): self;

    /**
     * Get agent request
     *
     * @return string|null
     */
    public function getAgentRequest(): ?string;

    /**
     * Set agent request
     *
     * @param string $agentRequest
     * @return $this
     */
    public function setAgentRequest(string $agentRequest): self;

    /**
     * Get agent response
     *
     * @return string|null
     */
    public function getAgentResponse(): ?string;

    /**
     * Set agent response
     *
     * @param string $agentResponse
     * @return $this
     */
    public function setAgentResponse(string $agentResponse): self;

    /**
     * Get status
     *
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * Set status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Get outcome
     *
     * @return string|null
     */
    public function getOutcome(): ?string;

    /**
     * Set outcome
     *
     * @param string $outcome
     * @return $this
     */
    public function setOutcome(string $outcome): self;

    /**
     * Get execution time in milliseconds
     *
     * @return int|null
     */
    public function getExecutionTimeMs(): ?int;

    /**
     * Set execution time in milliseconds
     *
     * @param int $executionTimeMs
     * @return $this
     */
    public function setExecutionTimeMs(int $executionTimeMs): self;

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set created at
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set updated at
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
