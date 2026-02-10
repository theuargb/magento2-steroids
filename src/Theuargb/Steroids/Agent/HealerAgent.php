<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Agent;

use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\SystemPrompt;
use Theuargb\Steroids\Agent\Provider\OpenAIRealtime\OpenAIRealtimeProvider;
use NeuronAI\Chat\Messages\UserMessage;
use Theuargb\Steroids\Agent\Tool\EvalPhpTool;
use Theuargb\Steroids\Agent\Tool\ReadFileTool;
use Theuargb\Steroids\Agent\Tool\WriteFileTool;
use Theuargb\Steroids\Agent\Tool\MagentoCliTool;
use Theuargb\Steroids\Agent\Tool\ClearCacheTool;
use Theuargb\Steroids\Agent\Tool\GetConfigTool;
use Theuargb\Steroids\Agent\Tool\QueryDatabaseTool;
use Theuargb\Steroids\Agent\Result\HealResult;

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
    private bool $allowFileWrites = false;
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
        $this->allowFileWrites = (bool) ($options['allow_file_writes'] ?? false);
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

        if ($this->llmProvider === 'openai_realtime') {
            return new OpenAIRealtimeProvider(
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
                'You are an expert Magento 2 self-healing agent executing live inside the PHP-FPM process that encountered the exception.',
                'You share the same memory space, ObjectManager, request state, and database connection as the failing request.',
                'Every tool call you make runs in THIS thread — changes take effect immediately in the live request context.',
                'Your eval_php runs in the live Magento bootstrap. ObjectManager::getInstance() works. You can fix things in-memory (adjust registry values, correct plugin results) or persist patches to disk if file writes are allowed.',
                "Magento version: {$this->magentoVersion}.",
            ],
            steps: [
                'Analyze the exception context provided to determine the root cause.',
                'Use available tools to investigate — read source files, check configs, query the database.',
                'Determine a fix strategy: in-memory patch via eval_php, cache clear, config adjustment, or file write.',
                'Implement the fix using the appropriate tools.',
                'Verify the fix by testing that the error condition is resolved.',
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

        // If file writes are disabled, remove write_file tool
        if (!$this->allowFileWrites) {
            unset($allTools['write_file']);
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
