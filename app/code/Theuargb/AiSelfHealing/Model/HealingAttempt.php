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

    public function getEntityId()
    {
        $v = $this->getData(self::ENTITY_ID);
        return $v !== null ? (int) $v : null;
    }

    public function setEntityId($entityId)
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

    public function getRequestMethod(): ?string
    {
        return $this->getData(self::REQUEST_METHOD);
    }

    public function setRequestMethod(string $method): HealingAttemptInterface
    {
        return $this->setData(self::REQUEST_METHOD, $method);
    }

    public function getExceptionClass(): ?string
    {
        return $this->getData(self::EXCEPTION_CLASS);
    }

    public function setExceptionClass(string $class): HealingAttemptInterface
    {
        return $this->setData(self::EXCEPTION_CLASS, $class);
    }

    public function getErrorMessage(): ?string
    {
        return $this->getData(self::ERROR_MESSAGE);
    }

    public function setErrorMessage(string $errorMessage): HealingAttemptInterface
    {
        return $this->setData(self::ERROR_MESSAGE, $errorMessage);
    }

    public function getExceptionFile(): ?string
    {
        return $this->getData(self::EXCEPTION_FILE);
    }

    public function setExceptionFile(string $file): HealingAttemptInterface
    {
        return $this->setData(self::EXCEPTION_FILE, $file);
    }

    public function getExceptionLine(): ?int
    {
        $v = $this->getData(self::EXCEPTION_LINE);
        return $v !== null ? (int) $v : null;
    }

    public function setExceptionLine(int $line): HealingAttemptInterface
    {
        return $this->setData(self::EXCEPTION_LINE, $line);
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

    public function getUrlRulePrompt(): ?string
    {
        return $this->getData(self::URL_RULE_PROMPT);
    }

    public function setUrlRulePrompt(?string $prompt): HealingAttemptInterface
    {
        return $this->setData(self::URL_RULE_PROMPT, $prompt);
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

    public function getAgentReasoningLog(): ?string
    {
        return $this->getData(self::AGENT_REASONING_LOG);
    }

    public function setAgentReasoningLog(?string $log): HealingAttemptInterface
    {
        return $this->setData(self::AGENT_REASONING_LOG, $log);
    }

    public function getAgentActionsTakenJson(): ?string
    {
        return $this->getData(self::AGENT_ACTIONS_TAKEN_JSON);
    }

    public function setAgentActionsTakenJson(?string $json): HealingAttemptInterface
    {
        return $this->setData(self::AGENT_ACTIONS_TAKEN_JSON, $json);
    }

    public function getAgentToolCallsCount(): ?int
    {
        $v = $this->getData(self::AGENT_TOOL_CALLS_COUNT);
        return $v !== null ? (int) $v : null;
    }

    public function setAgentToolCallsCount(?int $count): HealingAttemptInterface
    {
        return $this->setData(self::AGENT_TOOL_CALLS_COUNT, $count);
    }

    public function getLlmModelUsed(): ?string
    {
        return $this->getData(self::LLM_MODEL_USED);
    }

    public function setLlmModelUsed(?string $model): HealingAttemptInterface
    {
        return $this->setData(self::LLM_MODEL_USED, $model);
    }

    public function getLlmTokensUsed(): ?int
    {
        $v = $this->getData(self::LLM_TOKENS_USED);
        return $v !== null ? (int) $v : null;
    }

    public function setLlmTokensUsed(?int $tokens): HealingAttemptInterface
    {
        return $this->setData(self::LLM_TOKENS_USED, $tokens);
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

    public function getOutcomeDetail(): ?string
    {
        return $this->getData(self::OUTCOME_DETAIL);
    }

    public function setOutcomeDetail(?string $detail): HealingAttemptInterface
    {
        return $this->setData(self::OUTCOME_DETAIL, $detail);
    }

    public function getHealedDiff(): ?string
    {
        return $this->getData(self::HEALED_DIFF);
    }

    public function setHealedDiff(?string $diff): HealingAttemptInterface
    {
        return $this->setData(self::HEALED_DIFF, $diff);
    }

    public function getFallbackHtmlReturned(): ?string
    {
        return $this->getData(self::FALLBACK_HTML_RETURNED);
    }

    public function setFallbackHtmlReturned(?string $html): HealingAttemptInterface
    {
        return $this->setData(self::FALLBACK_HTML_RETURNED, $html);
    }

    public function getExecutionTimeMs(): ?int
    {
        $v = $this->getData(self::EXECUTION_TIME_MS);
        return $v !== null ? (int) $v : null;
    }

    public function setExecutionTimeMs(int $executionTimeMs): HealingAttemptInterface
    {
        return $this->setData(self::EXECUTION_TIME_MS, $executionTimeMs);
    }

    public function getAgentDurationSeconds(): ?float
    {
        $v = $this->getData(self::AGENT_DURATION_SECONDS);
        return $v !== null ? (float) $v : null;
    }

    public function setAgentDurationSeconds(?float $seconds): HealingAttemptInterface
    {
        return $this->setData(self::AGENT_DURATION_SECONDS, $seconds);
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
