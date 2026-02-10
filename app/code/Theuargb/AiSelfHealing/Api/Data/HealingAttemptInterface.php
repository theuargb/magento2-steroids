<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Api\Data;

interface HealingAttemptInterface
{
    public const ENTITY_ID = 'entity_id';
    public const FINGERPRINT = 'fingerprint';
    public const URL = 'url';
    public const REQUEST_METHOD = 'request_method';
    public const EXCEPTION_CLASS = 'exception_class';
    public const ERROR_MESSAGE = 'error_message';
    public const EXCEPTION_FILE = 'exception_file';
    public const EXCEPTION_LINE = 'exception_line';
    public const ERROR_TRACE = 'error_trace';
    public const CONTEXT_SNAPSHOT = 'context_snapshot';
    public const URL_RULE_PROMPT = 'url_rule_prompt';
    public const AGENT_REQUEST = 'agent_request';
    public const AGENT_RESPONSE = 'agent_response';
    public const AGENT_REASONING_LOG = 'agent_reasoning_log';
    public const AGENT_ACTIONS_TAKEN_JSON = 'agent_actions_taken_json';
    public const AGENT_TOOL_CALLS_COUNT = 'agent_tool_calls_count';
    public const LLM_MODEL_USED = 'llm_model_used';
    public const LLM_TOKENS_USED = 'llm_tokens_used';
    public const STATUS = 'status';
    public const OUTCOME = 'outcome';
    public const OUTCOME_DETAIL = 'outcome_detail';
    public const HEALED_DIFF = 'healed_diff';
    public const FALLBACK_HTML_RETURNED = 'fallback_html_returned';
    public const EXECUTION_TIME_MS = 'execution_time_ms';
    public const AGENT_DURATION_SECONDS = 'agent_duration_seconds';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /** @return int|null */
    public function getEntityId();
    /** @param int $entityId @return $this */
    public function setEntityId($entityId);

    /** @return string|null */
    public function getFingerprint(): ?string;
    /** @param string $fingerprint @return $this */
    public function setFingerprint(string $fingerprint): self;

    /** @return string|null */
    public function getUrl(): ?string;
    /** @param string $url @return $this */
    public function setUrl(string $url): self;

    /** @return string|null */
    public function getRequestMethod(): ?string;
    /** @param string $method @return $this */
    public function setRequestMethod(string $method): self;

    /** @return string|null */
    public function getExceptionClass(): ?string;
    /** @param string $class @return $this */
    public function setExceptionClass(string $class): self;

    /** @return string|null */
    public function getErrorMessage(): ?string;
    /** @param string $errorMessage @return $this */
    public function setErrorMessage(string $errorMessage): self;

    /** @return string|null */
    public function getExceptionFile(): ?string;
    /** @param string $file @return $this */
    public function setExceptionFile(string $file): self;

    /** @return int|null */
    public function getExceptionLine(): ?int;
    /** @param int $line @return $this */
    public function setExceptionLine(int $line): self;

    /** @return string|null */
    public function getErrorTrace(): ?string;
    /** @param string $errorTrace @return $this */
    public function setErrorTrace(string $errorTrace): self;

    /** @return string|null */
    public function getContextSnapshot(): ?string;
    /** @param string $contextSnapshot @return $this */
    public function setContextSnapshot(string $contextSnapshot): self;

    /** @return string|null */
    public function getUrlRulePrompt(): ?string;
    /** @param string|null $prompt @return $this */
    public function setUrlRulePrompt(?string $prompt): self;

    /** @return string|null */
    public function getAgentRequest(): ?string;
    /** @param string $agentRequest @return $this */
    public function setAgentRequest(string $agentRequest): self;

    /** @return string|null */
    public function getAgentResponse(): ?string;
    /** @param string $agentResponse @return $this */
    public function setAgentResponse(string $agentResponse): self;

    /** @return string|null */
    public function getAgentReasoningLog(): ?string;
    /** @param string|null $log @return $this */
    public function setAgentReasoningLog(?string $log): self;

    /** @return string|null */
    public function getAgentActionsTakenJson(): ?string;
    /** @param string|null $json @return $this */
    public function setAgentActionsTakenJson(?string $json): self;

    /** @return int|null */
    public function getAgentToolCallsCount(): ?int;
    /** @param int|null $count @return $this */
    public function setAgentToolCallsCount(?int $count): self;

    /** @return string|null */
    public function getLlmModelUsed(): ?string;
    /** @param string|null $model @return $this */
    public function setLlmModelUsed(?string $model): self;

    /** @return int|null */
    public function getLlmTokensUsed(): ?int;
    /** @param int|null $tokens @return $this */
    public function setLlmTokensUsed(?int $tokens): self;

    /** @return string|null */
    public function getStatus(): ?string;
    /** @param string $status @return $this */
    public function setStatus(string $status): self;

    /** @return string|null */
    public function getOutcome(): ?string;
    /** @param string $outcome @return $this */
    public function setOutcome(string $outcome): self;

    /** @return string|null */
    public function getOutcomeDetail(): ?string;
    /** @param string|null $detail @return $this */
    public function setOutcomeDetail(?string $detail): self;

    /** @return string|null */
    public function getHealedDiff(): ?string;
    /** @param string|null $diff @return $this */
    public function setHealedDiff(?string $diff): self;

    /** @return string|null */
    public function getFallbackHtmlReturned(): ?string;
    /** @param string|null $html @return $this */
    public function setFallbackHtmlReturned(?string $html): self;

    /** @return int|null */
    public function getExecutionTimeMs(): ?int;
    /** @param int $executionTimeMs @return $this */
    public function setExecutionTimeMs(int $executionTimeMs): self;

    /** @return float|null */
    public function getAgentDurationSeconds(): ?float;
    /** @param float|null $seconds @return $this */
    public function setAgentDurationSeconds(?float $seconds): self;

    /** @return string|null */
    public function getCreatedAt(): ?string;
    /** @param string $createdAt @return $this */
    public function setCreatedAt(string $createdAt): self;

    /** @return string|null */
    public function getUpdatedAt(): ?string;
    /** @param string $updatedAt @return $this */
    public function setUpdatedAt(string $updatedAt): self;
}
