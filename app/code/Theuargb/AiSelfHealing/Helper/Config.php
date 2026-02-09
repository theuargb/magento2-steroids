<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'ai_self_healing/general/enabled';
    private const XML_PATH_MODE = 'ai_self_healing/general/mode';
    private const XML_PATH_AGENT_ENDPOINT = 'ai_self_healing/agent/endpoint_url';
    private const XML_PATH_HEAL_TIMEOUT = 'ai_self_healing/agent/heal_timeout_seconds';
    private const XML_PATH_FALLBACK_TIMEOUT = 'ai_self_healing/agent/fallback_timeout_seconds';
    private const XML_PATH_MAX_TOOL_CALLS = 'ai_self_healing/agent/max_tool_calls';
    private const XML_PATH_URL_STRATEGY = 'ai_self_healing/url_filters/strategy';
    private const XML_PATH_URL_PATTERNS = 'ai_self_healing/url_filters/patterns';
    private const XML_PATH_EXCLUDE_PATTERNS = 'ai_self_healing/url_filters/exclude_patterns';
    private const XML_PATH_RESPONSE_REWRITE_ENABLED = 'ai_self_healing/response_rewrite/enabled';
    private const XML_PATH_RESPONSE_REWRITE_PATTERNS = 'ai_self_healing/response_rewrite/allowed_url_patterns';
    private const XML_PATH_RESPONSE_HTTP_STATUS = 'ai_self_healing/response_rewrite/http_status_code';
    private const XML_PATH_MAX_ATTEMPTS = 'ai_self_healing/safety/max_attempts_per_fingerprint_per_hour';
    private const XML_PATH_MAX_CONCURRENT = 'ai_self_healing/safety/max_concurrent_healings';
    private const XML_PATH_DISALLOWED_TOOLS = 'ai_self_healing/safety/disallowed_tool_actions';
    private const XML_PATH_READONLY_MODE = 'ai_self_healing/safety/readonly_mode';

    public function __construct(Context $context)
    {
        parent::__construct($context);
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
        );
    }

    public function getAgentEndpoint(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_AGENT_ENDPOINT,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getHealTimeout(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_HEAL_TIMEOUT,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getFallbackTimeout(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_FALLBACK_TIMEOUT,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getMaxToolCalls(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_TOOL_CALLS,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getUrlStrategy(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_URL_STRATEGY,
            ScopeInterface::SCOPE_STORE
        );
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
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_RESPONSE_HTTP_STATUS,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getMaxAttemptsPerHour(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_ATTEMPTS,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getMaxConcurrentHealings(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_CONCURRENT,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getDisallowedToolActions(): array
    {
        $tools = $this->scopeConfig->getValue(
            self::XML_PATH_DISALLOWED_TOOLS,
            ScopeInterface::SCOPE_STORE
        );
        return $tools ? array_filter(array_map('trim', explode(',', $tools))) : [];
    }

    public function isReadonlyMode(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_READONLY_MODE,
            ScopeInterface::SCOPE_STORE
        );
    }
}
