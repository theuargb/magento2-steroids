<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'ai_self_healing/general/enabled';
    private const XML_PATH_MODE = 'ai_self_healing/general/mode';
    private const XML_PATH_LLM_PROVIDER = 'ai_self_healing/llm/provider';
    private const XML_PATH_LLM_API_KEY = 'ai_self_healing/llm/api_key';
    private const XML_PATH_LLM_MODEL = 'ai_self_healing/llm/model';
    private const XML_PATH_LLM_BASE_URL = 'ai_self_healing/llm/base_url';
    private const XML_PATH_HEAL_TIMEOUT = 'ai_self_healing/agent/heal_timeout_seconds';
    private const XML_PATH_FALLBACK_TIMEOUT = 'ai_self_healing/agent/fallback_timeout_seconds';
    private const XML_PATH_MAX_TOOL_CALLS = 'ai_self_healing/agent/max_tool_calls';
    private const XML_PATH_URL_STRATEGY = 'ai_self_healing/url_filters/strategy';
    private const XML_PATH_URL_PATTERNS = 'ai_self_healing/url_filters/patterns';
    private const XML_PATH_EXCLUDE_PATTERNS = 'ai_self_healing/url_filters/exclude_patterns';
    private const XML_PATH_URL_RULES = 'ai_self_healing/url_filters/url_rules';
    private const XML_PATH_RESPONSE_REWRITE_ENABLED = 'ai_self_healing/response_rewrite/enabled';
    private const XML_PATH_RESPONSE_REWRITE_PATTERNS = 'ai_self_healing/response_rewrite/allowed_url_patterns';
    private const XML_PATH_RESPONSE_HTTP_STATUS = 'ai_self_healing/response_rewrite/http_status_code';
    private const XML_PATH_SNAPSHOT_FREQUENCY = 'ai_self_healing/snapshot/cron_frequency';
    private const XML_PATH_MAX_ATTEMPTS = 'ai_self_healing/safety/max_attempts_per_fingerprint_per_hour';
    private const XML_PATH_MAX_CONCURRENT = 'ai_self_healing/safety/max_concurrent_healings';
    private const XML_PATH_DISALLOWED_TOOLS = 'ai_self_healing/safety/disallowed_tool_actions';
    private const XML_PATH_READONLY_MODE = 'ai_self_healing/safety/readonly_mode';

    private Json $json;
    private EncryptorInterface $encryptor;

    public function __construct(
        Context $context,
        Json $json,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->json = $json;
        $this->encryptor = $encryptor;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getMode(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_MODE,
            ScopeInterface::SCOPE_STORE
        ) ?: 'monitor_only';
    }

    public function getLlmProvider(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_PROVIDER,
            ScopeInterface::SCOPE_STORE
        ) ?: 'openai';
    }

    public function getLlmApiKey(): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_API_KEY,
            ScopeInterface::SCOPE_STORE
        );
        return $value ? $this->encryptor->decrypt($value) : '';
    }

    public function getLlmModel(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_MODEL,
            ScopeInterface::SCOPE_STORE
        ) ?: 'gpt-4o';
    }

    public function getLlmBaseUrl(): ?string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_BASE_URL,
            ScopeInterface::SCOPE_STORE
        );
        return !empty($value) ? $value : null;
    }

    public function getHealTimeout(): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::XML_PATH_HEAL_TIMEOUT,
            ScopeInterface::SCOPE_STORE
        ) ?: 12);
    }

    public function getFallbackTimeout(): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::XML_PATH_FALLBACK_TIMEOUT,
            ScopeInterface::SCOPE_STORE
        ) ?: 8);
    }

    public function getMaxToolCalls(): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::XML_PATH_MAX_TOOL_CALLS,
            ScopeInterface::SCOPE_STORE
        ) ?: 10);
    }

    public function getUrlStrategy(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_URL_STRATEGY,
            ScopeInterface::SCOPE_STORE
        ) ?: 'all';
    }

    public function getUrlPatterns(): array
    {
        $patterns = $this->scopeConfig->getValue(
            self::XML_PATH_URL_PATTERNS,
            ScopeInterface::SCOPE_STORE
        );
        return $patterns ? array_filter(array_map('trim', explode("\n", $patterns))) : [];
    }

    public function getExcludePatterns(): array
    {
        $patterns = $this->scopeConfig->getValue(
            self::XML_PATH_EXCLUDE_PATTERNS,
            ScopeInterface::SCOPE_STORE
        );
        return $patterns ? array_filter(array_map('trim', explode("\n", $patterns))) : [];
    }

    /**
     * Get URL rules with per-URL prompt configuration.
     * Each rule is an array with: pattern, prompt, response_rewrite, enabled
     *
     * @return array
     */
    public function getUrlRules(): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_URL_RULES,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            try {
                $value = $this->json->unserialize($value);
            } catch (\Exception $e) {
                return [];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        // Remove the __empty row that Magento's dynamic rows may add
        unset($value['__empty']);

        return array_values($value);
    }

    public function isResponseRewriteEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_RESPONSE_REWRITE_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getResponseRewritePatterns(): array
    {
        $patterns = $this->scopeConfig->getValue(
            self::XML_PATH_RESPONSE_REWRITE_PATTERNS,
            ScopeInterface::SCOPE_STORE
        );
        return $patterns ? array_filter(array_map('trim', explode("\n", $patterns))) : [];
    }

    public function getResponseHttpStatus(): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::XML_PATH_RESPONSE_HTTP_STATUS,
            ScopeInterface::SCOPE_STORE
        ) ?: 200);
    }

    public function getFallbackHttpStatusCode(): int
    {
        return $this->getResponseHttpStatus();
    }

    public function getSnapshotFrequency(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_SNAPSHOT_FREQUENCY,
            ScopeInterface::SCOPE_STORE
        ) ?: 'every_6h';
    }

    public function getMaxAttemptsPerHour(): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::XML_PATH_MAX_ATTEMPTS,
            ScopeInterface::SCOPE_STORE
        ) ?: 3);
    }

    public function getMaxConcurrentHealings(): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::XML_PATH_MAX_CONCURRENT,
            ScopeInterface::SCOPE_STORE
        ) ?: 2);
    }

    public function getDisallowedToolActions(): array
    {
        $tools = $this->scopeConfig->getValue(
            self::XML_PATH_DISALLOWED_TOOLS,
            ScopeInterface::SCOPE_STORE
        );
        if (!$tools) {
            return [];
        }
        // Support both comma-separated and newline-separated
        $tools = str_replace("\n", ',', $tools);
        return array_filter(array_map('trim', explode(',', $tools)));
    }

    public function isReadonlyMode(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_READONLY_MODE,
            ScopeInterface::SCOPE_STORE
        );
    }
}
