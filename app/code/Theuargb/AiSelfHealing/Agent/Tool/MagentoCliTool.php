<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Tool;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

/**
 * Execute a bin/magento CLI command.
 * Certain destructive commands are blocked for safety.
 */
class MagentoCliTool extends Tool
{
    private const BLOCKED_COMMANDS = [
        'setup:uninstall',
        'setup:rollback',
        'app:config:import',
        'app:config:dump',
        'encryption-key:change',
        'admin:user:create',
        'admin:user:unlock',
        'setup:backup',
        'deploy:mode:set',
    ];

    public function __construct(private readonly string $magentoRoot)
    {
        parent::__construct(
            'magento_cli',
            'Run a Magento CLI (bin/magento) command. '
            . 'Useful for cache management, reindexing, setup commands, '
            . 'and diagnostics. Certain destructive commands are blocked.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'command',
                type: PropertyType::STRING,
                description: "Magento CLI command without 'bin/magento' prefix. "
                    . "E.g. 'cache:flush', 'module:status', 'setup:di:compile'",
                required: true
            ),
        ];
    }

    public function __invoke(string $command): string
    {
        $command = trim($command);
        if ($command === '') {
            return 'ERROR: No command provided.';
        }

        $baseCmd = explode(' ', $command)[0];
        if (in_array($baseCmd, self::BLOCKED_COMMANDS, true)) {
            return "ERROR: Command '{$baseCmd}' is blocked for safety reasons.";
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

        // Cap output
        if (strlen($result) > 50000) {
            $result = substr($result, 0, 50000) . "\n... [truncated]";
        }

        return $result;
    }
}
