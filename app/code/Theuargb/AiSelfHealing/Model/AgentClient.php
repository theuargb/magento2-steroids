<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Theuargb\AiSelfHealing\Api\AgentClientInterface;
use Theuargb\AiSelfHealing\Helper\Config;
use Theuargb\AiSelfHealing\Agent\HealerAgent;
use Theuargb\AiSelfHealing\Agent\FallbackAgent;
use Theuargb\AiSelfHealing\Agent\Result\HealResult;
use Theuargb\AiSelfHealing\Agent\Result\FallbackResult;
use Theuargb\AiSelfHealing\Agent\Observer\AgentLogger;

/**
 * Agent client — runs the neuron-ai agent loop directly in-process.
 *
 * All tool calls (eval_php, read_file, etc.) execute in the SAME
 * PHP-FPM thread, giving the agent live access to ObjectManager,
 * request state, and runtime variables. No external service needed.
 */
class AgentClient implements AgentClientInterface
{
    public function __construct(
        private readonly Json $json,
        private readonly Config $config,
        private readonly DirectoryList $directoryList,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Run the healing agent in-process.
     *
     * The neuron-ai agent loop runs directly here — tool calls execute
     * in THIS PHP-FPM process, giving the agent live access to
     * ObjectManager, request state, and runtime variables.
     */
    public function requestHealing(array $context, int $timeout): HealResult
    {
        $this->logger->info('[AgentClient] Starting in-process healing agent');

        $contextStr = $this->json->serialize($context);

        // Enrich context with per-URL prompt if present
        $userPrompt = $context['user_defined_prompt'] ?? null;
        if ($userPrompt) {
            $contextStr = "=== ADMIN-DEFINED INSTRUCTIONS FOR THIS URL ===\n"
                . $userPrompt . "\n"
                . "=== END ADMIN INSTRUCTIONS ===\n\n"
                . $contextStr;
        }

        $magentoVersion = ($context['magento']['version'] ?? 'unknown')
            . ' ' . ($context['magento']['edition'] ?? '');

        try {
            $agent = HealerAgent::make();
            $agent->observe(new AgentLogger($this->logger, '[HealerAgent]'));
            $agent->configure([
                'llm_provider'    => $this->config->getLlmProvider(),
                'llm_api_key'     => $this->config->getLlmApiKey(),
                'llm_model'       => $this->config->getLlmModel(),
                'llm_base_url'    => $this->config->getLlmBaseUrl(),
                'max_tool_calls'  => $this->config->getMaxToolCalls(),
                'disallowed_tools' => $this->config->getDisallowedToolActions(),
                'readonly_mode'   => $this->config->isReadonlyMode(),
                'magento_root'    => $this->directoryList->getRoot(),
                'exception_context' => $contextStr,
                'magento_version'   => $magentoVersion,
            ]);

            $this->logger->info('[AgentClient] Sending prompt to healer agent', [
                'provider' => $this->config->getLlmProvider(),
                'model'    => $this->config->getLlmModel(),
                'prompt_length' => mb_strlen($contextStr),
            ]);

            $result = $agent->heal();

            $this->logger->info('[AgentClient] Healing completed', [
                'is_healed'    => $result->isHealed(),
                'duration'     => $result->getDurationSeconds(),
                'model_used'   => $result->getModelUsed(),
                'tokens_used'  => $result->getTokensUsed(),
                'tool_calls'   => $result->getToolCallsCount(),
                'summary'      => mb_substr($result->getSummary(), 0, 500),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('[AgentClient] Agent error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate fallback HTML using the in-process agent.
     */
    public function requestFallbackHtml(
        array $context,
        string $homepageHtml,
        string $homepageCss,
        int $timeout
    ): FallbackResult {
        $this->logger->info('[AgentClient] Starting fallback HTML generation');

        // Sanitize: never send raw traces to the HTML generation
        $safeErrorContext = sprintf(
            'The page at %s encountered a temporary issue. Error type: %s',
            $context['request']['uri'] ?? '/',
            $context['exception']['class'] ?? 'Unknown'
        );

        try {
            $agent = FallbackAgent::make();
            $agent->observe(new AgentLogger($this->logger, '[FallbackAgent]'));
            $agent->configure([
                'llm_provider' => $this->config->getLlmProvider(),
                'llm_api_key'  => $this->config->getLlmApiKey(),
                'llm_model'    => $this->config->getLlmModel(),
                'llm_base_url' => $this->config->getLlmBaseUrl(),
            ]);

            return $agent->generateFallback(
                url: $context['request']['uri'] ?? '/',
                errorContext: $safeErrorContext,
                homepageHtml: $homepageHtml,
                homepageCss: $homepageCss
            );
        } catch (\Throwable $e) {
            $this->logger->error('[AgentClient] Fallback generation error: ' . $e->getMessage());
            return new FallbackResult(hasHtml: false, html: '');
        }
    }

    /**
     * The agent runs in-process — always available if LLM API key is configured.
     */
    public function isAvailable(): bool
    {
        return !empty($this->config->getLlmApiKey());
    }
}
