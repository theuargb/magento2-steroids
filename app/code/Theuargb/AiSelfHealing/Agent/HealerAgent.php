<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent;

use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\SystemPrompt;
use NeuronAI\Chat\Messages\UserMessage;
use Theuargb\AiSelfHealing\Agent\Tool\EvalPhpTool;
use Theuargb\AiSelfHealing\Agent\Tool\ReadFileTool;
use Theuargb\AiSelfHealing\Agent\Tool\WriteFileTool;
use Theuargb\AiSelfHealing\Agent\Tool\MagentoCliTool;
use Theuargb\AiSelfHealing\Agent\Tool\ClearCacheTool;
use Theuargb\AiSelfHealing\Agent\Tool\GetConfigTool;
use Theuargb\AiSelfHealing\Agent\Tool\QueryDatabaseTool;
use Theuargb\AiSelfHealing\Agent\Result\HealResult;

/**
 * AI Healer Agent — runs directly in the PHP-FPM process via neuron-ai.
 *
 * Uses the ReAct (Reason + Act) pattern: the LLM reasons about the
 * exception, calls tools to diagnose/fix, and verifies the result.
 * Because this runs in-process, eval_php has direct access to
 * ObjectManager, request state, and all runtime variables.
 */
class HealerAgent extends Agent
{
    private string $llmProvider = 'openai';
    private string $llmApiKey = '';
    private string $llmModel = 'gpt-4o';
    private ?string $llmBaseUrl = null;
    private int $maxToolCalls = 10;
    private array $disallowedTools = [];
    private bool $readonlyMode = false;
    private string $magentoRoot = '';
    private string $exceptionContext = '';
    private string $magentoVersion = '';

    /**
     * Configure the agent before running.
     */
    public function configure(array $options): self
    {
        $this->llmProvider = $options['llm_provider'] ?? 'openai';
        $this->llmApiKey = $options['llm_api_key'] ?? '';
        $this->llmModel = $options['llm_model'] ?? 'gpt-4o';
        $this->llmBaseUrl = $options['llm_base_url'] ?? null;
        $this->maxToolCalls = (int) ($options['max_tool_calls'] ?? 10);
        $this->disallowedTools = $options['disallowed_tools'] ?? [];
        $this->readonlyMode = (bool) ($options['readonly_mode'] ?? false);
        $this->magentoRoot = $options['magento_root'] ?? '';
        $this->exceptionContext = $options['exception_context'] ?? '';
        $this->magentoVersion = $options['magento_version'] ?? '';

        return $this;
    }

    protected function provider(): AIProviderInterface
    {
        if ($this->llmProvider === 'anthropic') {
            return new Anthropic(
                key: $this->llmApiKey,
                model: $this->llmModel,
            );
        }

        // OpenAI-compatible (including custom base URLs for proxies / self-hosted)
        if (!empty($this->llmBaseUrl)) {
            return new OpenAILike(
                baseUri: $this->llmBaseUrl,
                key: $this->llmApiKey,
                model: $this->llmModel,
            );
        }

        return new OpenAI(
            key: $this->llmApiKey,
            model: $this->llmModel,
        );
    }

    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are an expert Magento 2 self-healing agent running inside the live PHP-FPM process.',
                'You have direct access to the Magento ObjectManager, filesystem, CLI, database, and configuration.',
                'Your goal is to diagnose and fix exceptions that occur during request processing.',
                "Magento version: {$this->magentoVersion}.",
            ],
            steps: [
                'Analyze the exception context provided to determine the root cause.',
                'Use available tools to investigate further — read source files, check configs, query the database.',
                'Determine a fix strategy and implement it using the tools (write files, clear caches, run CLI commands, or eval PHP).',
                'After applying fixes, verify the fix by using eval_php to test that the error condition is resolved.',
                'Report your findings clearly.',
            ],
            output: [
                'Provide a clear summary of what went wrong and what you did to fix it.',
                'If you could not fix the issue, explain why and what you tried.',
                'Start your response with either "HEALED:" or "FAILED:" followed by a brief explanation.',
            ]
        );
    }

    protected function tools(): array
    {
        $allTools = [
            'eval_php' => new EvalPhpTool(),
            'read_file' => new ReadFileTool($this->magentoRoot),
            'write_file' => new WriteFileTool($this->magentoRoot),
            'magento_cli' => new MagentoCliTool($this->magentoRoot),
            'clear_cache' => new ClearCacheTool($this->magentoRoot),
            'get_config' => new GetConfigTool(),
            'query_db' => new QueryDatabaseTool(),
        ];

        // Remove disallowed tools
        foreach ($this->disallowedTools as $toolName) {
            unset($allTools[$toolName]);
        }

        // In readonly mode, remove write-capable tools
        if ($this->readonlyMode) {
            unset($allTools['write_file'], $allTools['magento_cli'], $allTools['eval_php']);
        }

        return array_values($allTools);
    }

    /**
     * Run the healing flow and return a structured result.
     */
    public function heal(): HealResult
    {
        $startTime = microtime(true);
        $actionsLog = [];

        try {
            $prompt = $this->exceptionContext;

            $response = $this->toolMaxTries($this->maxToolCalls)
                ->chat(new UserMessage($prompt));

            $content = $response->getContent();
            $isHealed = str_starts_with(strtoupper($content), 'HEALED:');
            $summary = $content;

            // Extract token usage if available
            $tokensUsed = 0;
            $usage = $response->getUsage();
            if ($usage) {
                $tokensUsed = ($usage->inputTokens ?? 0) + ($usage->outputTokens ?? 0);
            }

            $duration = microtime(true) - $startTime;

            return new HealResult(
                isHealed: $isHealed,
                summary: $summary,
                reasoningLog: $summary,
                actionsTaken: $actionsLog,
                toolCallsCount: 0,
                modelUsed: $this->llmModel,
                tokensUsed: $tokensUsed,
                diff: null,
                failureReason: $isHealed ? null : $summary,
                durationSeconds: $duration
            );
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            return new HealResult(
                isHealed: false,
                summary: 'Agent error: ' . $e->getMessage(),
                reasoningLog: $e->getTraceAsString(),
                actionsTaken: $actionsLog,
                toolCallsCount: 0,
                modelUsed: $this->llmModel,
                tokensUsed: 0,
                diff: null,
                failureReason: $e->getMessage(),
                durationSeconds: $duration
            );
        }
    }
}
