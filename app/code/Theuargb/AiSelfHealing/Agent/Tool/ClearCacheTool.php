<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Tool;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

/**
 * Clear Magento cache. Supports specific cache types or full flush.
 */
class ClearCacheTool extends Tool
{
    private const ALLOWED_TYPES = [
        'all', 'config', 'layout', 'block_html', 'full_page', 'collections',
        'reflection', 'db_ddl', 'compiled_config', 'eav',
        'customer_notification', 'config_integration',
        'config_integration_api', 'config_webservice', 'translate',
    ];

    public function __construct(private readonly string $magentoRoot)
    {
        parent::__construct(
            'clear_cache',
            "Clear Magento cache. Use 'all' for a full flush or specify a type "
            . "like 'config', 'layout', 'block_html', 'full_page', etc. "
            . 'Essential after making config or code changes.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'cache_type',
                type: PropertyType::STRING,
                description: "Cache type to clear: 'all', 'config', 'layout', 'block_html', "
                    . "'full_page', 'collections', etc.",
                required: true
            ),
        ];
    }

    public function __invoke(string $cache_type = 'all'): string
    {
        if (!in_array($cache_type, self::ALLOWED_TYPES, true)) {
            return "ERROR: Unknown cache type '{$cache_type}'. Allowed: " . implode(', ', self::ALLOWED_TYPES);
        }

        if ($cache_type === 'all') {
            $command = 'cache:flush';
        } else {
            $command = "cache:clean {$cache_type}";
        }

        $fullCommand = sprintf(
            'cd %s && php bin/magento %s 2>&1',
            escapeshellarg($this->magentoRoot),
            $command
        );

        $output = [];
        $exitCode = 0;
        exec($fullCommand, $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $result .= "\n[exit code: {$exitCode}]";
        }

        return $result;
    }
}
