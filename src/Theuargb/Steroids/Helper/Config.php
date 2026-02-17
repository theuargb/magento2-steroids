<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Theuargb\Steroids\Model\Config\Source\LlmProvider;

class Config extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'steroids/general/enabled';
    private const XML_PATH_LLM_PROVIDER = 'steroids/llm/provider';
    private const XML_PATH_LLM_API_KEY = 'steroids/llm/api_key';
    private const XML_PATH_LLM_CUSTOM_PROVIDER = 'steroids/llm/custom_provider';
    private const XML_PATH_LLM_MODEL = 'steroids/llm/model';
    private const XML_PATH_LLM_BASE_URL = 'steroids/llm/base_url';
    private const XML_PATH_HEAL_TIMEOUT = 'steroids/agent/heal_timeout_seconds';
    private const XML_PATH_FALLBACK_TIMEOUT = 'steroids/agent/fallback_timeout_seconds';
    private const XML_PATH_MAX_TOOL_CALLS = 'steroids/agent/max_tool_calls';
    private const XML_PATH_URL_RULES = 'steroids/url_filters/url_rules';
    private const XML_PATH_DESIGN_JSON = 'steroids/design/design_json';
    private const XML_PATH_FIRECRAWL_API_KEY = 'steroids/design/firecrawl_api_key';
    private const XML_PATH_FIRECRAWL_STORE_URL = 'steroids/design/firecrawl_store_url';
    private const XML_PATH_MAX_ATTEMPTS = 'steroids/safety/max_attempts_per_fingerprint_per_hour';
    private const XML_PATH_MAX_CONCURRENT = 'steroids/safety/max_concurrent_healings';
    private const XML_PATH_DISALLOWED_TOOLS = 'steroids/safety/disallowed_tool_actions';
    private const XML_PATH_ALLOW_FILE_WRITES = 'steroids/safety/allow_file_writes';
    private const XML_PATH_FALLBACK_CACHE_TTL = 'steroids/agent/fallback_cache_ttl_seconds';

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

    public function getLlmProvider(): string
    {
        $preset = $this->getRawProvider();

        // Preset mode — resolve to actual provider type
        if (LlmProvider::isPreset($preset)) {
            return LlmProvider::resolveProviderType($preset);
        }

        // Custom mode — read the explicit provider type field
        if ($preset === 'custom') {
            return (string) $this->scopeConfig->getValue(
                self::XML_PATH_LLM_CUSTOM_PROVIDER,
                ScopeInterface::SCOPE_STORE
            ) ?: 'openai';
        }

        // Legacy values (openai, anthropic, etc.) — pass through
        return $preset;
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
        $preset = $this->getRawProvider();

        // Preset mode — use the pre-configured model
        if (LlmProvider::isPreset($preset)) {
            return LlmProvider::resolveModel($preset) ?? 'gpt-4o';
        }

        // Custom or legacy — read the model field
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_MODEL,
            ScopeInterface::SCOPE_STORE
        ) ?: 'gpt-4o';
    }

    public function getLlmBaseUrl(): ?string
    {
        $preset = $this->getRawProvider();

        // Preset mode — use the pre-configured base URL
        if (LlmProvider::isPreset($preset)) {
            return LlmProvider::resolveBaseUrl($preset);
        }

        // Custom or legacy — read the base_url field
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_BASE_URL,
            ScopeInterface::SCOPE_STORE
        );
        return !empty($value) ? $value : null;
    }

    /**
     * Get the raw provider/preset value from config (before resolution).
     */
    public function getRawProvider(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_PROVIDER,
            ScopeInterface::SCOPE_STORE
        ) ?: 'openai_gpt5';
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

    /**
     * Get free-form design JSON from admin configuration.
     *
     * Returns the parsed JSON as an associative array, or empty array if not configured.
     * The shape is intentionally free-form — generated by the admin from a page screenshot via ChatGPT.
     *
     * @return array
     */
    public function getDesignJson(): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_DESIGN_JSON,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($value)) {
            return [];
        }

        try {
            $parsed = $this->json->unserialize($value);
            return is_array($parsed) ? $parsed : [];
        } catch (\Exception $e) {
            return [];
        }
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

    public function isFileWriteAllowed(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_ALLOW_FILE_WRITES,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getFallbackCacheTtl(): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::XML_PATH_FALLBACK_CACHE_TTL,
            ScopeInterface::SCOPE_STORE
        ) ?: 3600);
    }

    /**
     * Get the Firecrawl API key (decrypted).
     */
    public function getFirecrawlApiKey(): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_FIRECRAWL_API_KEY,
            ScopeInterface::SCOPE_STORE
        );
        return $value ? $this->encryptor->decrypt($value) : '';
    }

    /**
     * Get the store URL configured for Firecrawl scraping.
     */
    public function getFirecrawlStoreUrl(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_FIRECRAWL_STORE_URL,
            ScopeInterface::SCOPE_STORE
        );
    }
}
