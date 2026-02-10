<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Tool;

use Magento\Framework\App\ObjectManager;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

/**
 * Execute PHP code inside the LIVE Magento 2 process.
 *
 * Runs via eval() in the same PHP-FPM thread as the customer request,
 * giving the agent direct access to ObjectManager, request, session,
 * and all runtime variables.
 */
class EvalPhpTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'eval_php',
            'Execute PHP code inside the LIVE Magento 2 process. '
            . 'Runs in the same PHP-FPM thread as the customer request, '
            . 'providing direct access to ObjectManager, request, session, '
            . 'and all runtime variables. Use for diagnostics, config checks, '
            . 'and targeted runtime fixes. Do NOT include <?php tags.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'code',
                type: PropertyType::STRING,
                description: 'PHP code to execute. $objectManager is available. No <?php tag needed.',
                required: true
            ),
        ];
    }

    public function __invoke(string $code): string
    {
        if (empty(trim($code))) {
            return json_encode(['error' => true, 'message' => 'No code provided']);
        }

        $objectManager = ObjectManager::getInstance();

        ob_start();
        try {
            // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
            eval($code);
        } catch (\Throwable $e) {
            ob_end_clean();
            return json_encode([
                'error'   => true,
                'class'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }

        $output = ob_get_clean();

        // Cap at 50 KB
        if (strlen($output) > 50000) {
            $output = substr($output, 0, 50000) . "\n... [truncated at 50KB]";
        }

        return $output;
    }
}
