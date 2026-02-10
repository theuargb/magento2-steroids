<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Theuargb\Steroids\Api\AgentClientInterface;
use Theuargb\Steroids\Api\HealingAttemptRepositoryInterface;
use Theuargb\Steroids\Api\Data\HealingAttemptInterfaceFactory;
use Theuargb\Steroids\Helper\Config;
use Theuargb\Steroids\Model\FallbackCache;
use Psr\Log\LoggerInterface;

class HealingOrchestrator
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlFilterMatcher $urlFilter,
        private readonly ContextCollector $contextCollector,
        private readonly AgentClientInterface $agentClient,
        private readonly FallbackResponseBuilder $fallbackBuilder,
        private readonly FallbackCache $fallbackCache,
        private readonly RequestRedispatcher $redispatcher,
        private readonly HealingAttemptRepositoryInterface $attemptRepository,
        private readonly HealingAttemptInterfaceFactory $attemptFactory,
        private readonly ConcurrencyGuard $concurrencyGuard,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly RequestInterface $request,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Main orchestration: returns a Response if healed/fallback,
     * or null if we should let the original exception propagate.
     */
    public function handle(\Throwable $e): ?ResponseInterface
    {
        $url = $this->request->getRequestUri();

        // 1. URL filter check
        if (!$this->urlFilter->shouldIntercept($url)) {
            return null;
        }

        // Get the matched rule for this URL
        $matchedRule = $this->urlFilter->getMatchingRule($url);
        if ($matchedRule === null) {
            return null;
        }

        // 2. Circuit breaker — same error too many times recently?
        $fingerprint = $this->contextCollector->computeFingerprint($e);
        if ($this->circuitBreaker->isOpen($fingerprint)) {
            $this->logger->info("[Steroids] Circuit breaker open for {$fingerprint}");
            return null;
        }

        // 3. Concurrency guard
        if (!$this->concurrencyGuard->acquire()) {
            $this->logger->info('[Steroids] Max concurrent healings reached, skipping');
            return null;
        }

        $attempt = $this->attemptFactory->create();
        $startTime = microtime(true);

        try {
            // 4. Collect context
            $context = $this->contextCollector->collect($e, $this->request);

            // Add prompts from matched rule
            $context['healer_prompt'] = $matchedRule['healer_prompt'] ?? null;
            $context['fallback_prompt'] = $matchedRule['fallback_prompt'] ?? null;

            $attempt->setUrl($url);
            $attempt->setRequestMethod($this->request->getMethod());
            $attempt->setExceptionClass(get_class($e));
            $attempt->setErrorMessage($e->getMessage());
            $attempt->setExceptionFile($e->getFile());
            $attempt->setExceptionLine($e->getLine());
            $attempt->setErrorTrace($e->getTraceAsString());
            $attempt->setFingerprint($fingerprint);
            $attempt->setContextSnapshot(json_encode($context));
            $attempt->setAgentRequest(json_encode($context));
            $attempt->setStatus('processing');

            // Record healer prompt if present
            if (!empty($context['healer_prompt'])) {
                $attempt->setUrlRulePrompt($context['healer_prompt']);
            }

            $this->circuitBreaker->recordAttempt($fingerprint);

            // 5. Check if healing is allowed for this rule
            $allowHealing = !empty($matchedRule['allow_healing']);
            $allowFallback = !empty($matchedRule['allow_fallback']);

            // 6. Call AI agent for healing if allowed
            if ($allowHealing) {
                $healResult = $this->agentClient->requestHealing(
                    context: $context,
                    timeout: $this->config->getHealTimeout()
                );

                $attempt->setAgentReasoningLog($healResult->getReasoningLog());
                $attempt->setAgentResponse(json_encode([
                    'is_healed' => $healResult->isHealed(),
                    'summary' => $healResult->getSummary(),
                    'tool_calls_count' => $healResult->getToolCallsCount(),
                    'model_used' => $healResult->getModelUsed(),
                    'tokens_used' => $healResult->getTokensUsed(),
                    'duration_seconds' => $healResult->getDurationSeconds(),
                ]));
                $attempt->setAgentActionsTakenJson(json_encode($healResult->getActionsTaken()));
                $attempt->setAgentToolCallsCount($healResult->getToolCallsCount());
                $attempt->setLlmModelUsed($healResult->getModelUsed());
                $attempt->setLlmTokensUsed($healResult->getTokensUsed());

                // 7a. Healing succeeded → redirect to pick up changes
                if ($healResult->isHealed()) {
                    $response = $this->redispatcher->buildRedirect();
                    $attempt->setOutcome('healed');
                    $attempt->setOutcomeDetail($healResult->getSummary());
                    $attempt->setHealedDiff($healResult->getDiff());
                    $attempt->setStatus('completed');
                    return $response;
                }
            } else {
                // Healing not allowed — skip healing step
                $attempt->setOutcome('healing_disabled');
                $attempt->setOutcomeDetail('Healing disabled for this URL rule');
            }

            // 7b. Healing failed or disabled → try fallback HTML if allowed
            if ($allowFallback) {
                $cacheFallback = !empty($matchedRule['cache_fallback']);

                // Check cache first
                if ($cacheFallback) {
                    $cachedResult = $this->fallbackCache->load($url, $fingerprint);
                    if ($cachedResult !== null && $cachedResult->hasResponse()) {
                        $response = $this->fallbackBuilder->build($cachedResult);
                        $attempt->setOutcome('fallback_cached');
                        $attempt->setFallbackHtmlReturned($cachedResult->getHtml());
                        $attempt->setOutcomeDetail('Served cached fallback response');
                        $attempt->setStatus('completed');
                        return $response;
                    }
                }

                // Generate fresh fallback via AI agent
                $designContext = $this->config->getDesignJson();
                $fallbackResult = $this->agentClient->requestFallbackHtml(
                    context: $context,
                    designContext: $designContext,
                    fallbackPrompt: $context['fallback_prompt'] ?? null,
                    timeout: $this->config->getFallbackTimeout()
                );

                if ($fallbackResult->hasResponse()) {
                    // Cache the result if enabled for this rule
                    if ($cacheFallback) {
                        $this->fallbackCache->save($url, $fingerprint, $fallbackResult);
                    }

                    $response = $this->fallbackBuilder->build($fallbackResult);
                    $attempt->setOutcome('fallback_html');
                    $attempt->setFallbackHtmlReturned($fallbackResult->getHtml());
                    $attempt->setOutcomeDetail(
                        !empty($designContext)
                            ? 'Served AI-generated fallback page with design reference'
                            : 'Served AI-generated fallback page (no design reference)'
                    );
                    $attempt->setStatus('completed');
                    return $response;
                }
            }

            // 7c. Nothing worked
            $attempt->setOutcome('failed');
            $attempt->setOutcomeDetail('Healing and fallback both unsuccessful');
            $attempt->setStatus('failed');
            return null;

        } catch (\Throwable $agentException) {
            $attempt->setOutcome('error');
            $attempt->setOutcomeDetail($agentException->getMessage());
            $attempt->setStatus('failed');
            $this->logger->error('[Steroids] Agent error: ' . $agentException->getMessage());
            return null;

        } finally {
            $duration = microtime(true) - $startTime;
            $attempt->setAgentDurationSeconds($duration);
            $attempt->setExecutionTimeMs((int) ($duration * 1000));
            try {
                $this->attemptRepository->save($attempt);
            } catch (\Throwable $saveError) {
                $this->logger->error('[Steroids] Failed to save attempt: ' . $saveError->getMessage());
            }
            $this->concurrencyGuard->release();
        }
    }
}
