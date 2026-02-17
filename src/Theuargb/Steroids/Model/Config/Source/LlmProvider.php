<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LlmProvider implements OptionSourceInterface
{
    /**
     * Preset definitions: provider_type, model, base_url are pre-configured.
     * In "custom" mode the admin controls all fields manually.
     */
    public const PRESETS = [
        'openai_realtime' => [
            'label'         => 'OpenAI Realtime',
            'provider_type' => 'openai_realtime',
            'model'         => 'gpt-realtime',
            'base_url'      => null,
        ],
        'openai_gpt5' => [
            'label'         => 'OpenAI GPT-5',
            'provider_type' => 'openai',
            'model'         => 'gpt-5',
            'base_url'      => null,
        ],
        'anthropic_haiku45' => [
            'label'         => 'Anthropic Haiku 4.5',
            'provider_type' => 'anthropic',
            'model'         => 'claude-haiku-4-5-20250514',
            'base_url'      => null,
        ],
        'google_gemini3_flash' => [
            'label'         => 'Google Gemini 3 Flash',
            'provider_type' => 'openai_compatible',
            'model'         => 'gemini-3.0-flash',
            'base_url'      => 'https://generativelanguage.googleapis.com/v1beta/openai/',
        ],
    ];

    public function toOptionArray(): array
    {
        $options = [];

        foreach (self::PRESETS as $value => $preset) {
            $options[] = ['value' => $value, 'label' => __($preset['label'])];
        }

        $options[] = ['value' => 'custom', 'label' => __('Custom (manual configuration)')];

        return $options;
    }

    /**
     * Check whether a given provider value is a known preset.
     */
    public static function isPreset(string $provider): bool
    {
        return isset(self::PRESETS[$provider]);
    }

    /**
     * Resolve the actual provider type for agents.
     */
    public static function resolveProviderType(string $provider): string
    {
        if (isset(self::PRESETS[$provider])) {
            return self::PRESETS[$provider]['provider_type'];
        }

        return $provider; // custom or legacy values
    }

    /**
     * Resolve the model name for a preset.
     */
    public static function resolveModel(string $provider): ?string
    {
        return self::PRESETS[$provider]['model'] ?? null;
    }

    /**
     * Resolve the base URL for a preset.
     */
    public static function resolveBaseUrl(string $provider): ?string
    {
        return self::PRESETS[$provider]['base_url'] ?? null;
    }
}
