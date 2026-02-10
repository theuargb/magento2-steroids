<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\App\Http as MagentoHttp;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Theuargb\AiSelfHealing\Api\AgentClientInterface;
use Theuargb\AiSelfHealing\Api\HealingAttemptRepositoryInterface;
use Theuargb\AiSelfHealing\Api\Data\HealingAttemptInterfaceFactory;
use Theuargb\AiSelfHealing\Helper\Config;
use Psr\Log\LoggerInterface;

class HealingOrchestrator
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlFilterMatcher $urlFilter,
        private readonly ResponseRewriteChecker $rewriteChecker,
        private readonly ContextCollector $contextCollector,
        private readonly AgentClientInterface $agentClient,
        private readonly HomepageSnapshotManager $snapshotManager,
        private readonly FallbackResponseBuilder $fallbackBuilder,
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
    public function handle(\Throwable $e, MagentoHttp $app): ?ResponseInterface
    {
        $url = $this->request->getRequestUri();

        // 1. URL filter check
        if (!$this->urlFilter->shouldIntercept($url)) {
            return null;
        }

        // 2. Circuit breaker — same error too many times recently?
        $fingerprint = $this->contextCollector->computeFingerprint($e);
        if ($this->circuitBreaker->isOpen($fingerprint)) {
            $this->logger->info("[AiSelfHealing] Circuit breaker open for {$fingerprint}");
            return null;
        }

        // 3. Concurrency guard
        if (!$this->concurrencyGuard->acquire()) {
            $this->logger->info('[AiSelfHealing] Max concurrent healings reached, skipping');
            return null;
        }

        $attempt = $this->attemptFactory->create();
        $startTime = microtime(true);

        try {
            // 4. Collect context (includes per-URL prompt if configured)
            $context = $this->contextCollector->collect($e, $this->request);

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

            // Record per-URL prompt if present
            $urlPrompt = $this->urlFilter->getMatchingPrompt($url);
            if ($urlPrompt !== null) {
                $attempt->setUrlRulePrompt($urlPrompt);
            }

            // 4a. Check mode — monitor_only just logs
            if ($this->config->getMode() === 'monitor_only') {
                $attempt->setStatus('monitored');
                $attempt->setOutcome('not_attempted');
                $attempt->setOutcomeDetail('Monitor-only mode active');
                return null;
            }

            $this->circuitBreaker->recordAttempt($fingerprint);

            // 5. Call AI agent for healing
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

            // 6a. Healing succeeded → re-dispatch
            if ($healResult->isHealed()) {
                try {
                    $response = $this->redispatcher->redispatch($app);
                    $attempt->setOutcome('healed');
                    $attempt->setOutcomeDetail($healResult->getSummary());
                    $attempt->setHealedDiff($healResult->getDiff());
                    $attempt->setStatus('completed');
                    return $response;
                } catch (\Throwable $redispatchError) {
                    $this->logger->warning(
                        '[AiSelfHealing] Re-dispatch failed after healing: ' . $redispatchError->getMessage()
                    );
                    // Fall through to fallback
                }
            }

            // 6b. Healing failed → try fallback HTML if allowed
            if ($this->rewriteChecker->isAllowed($url)) {
                $snapshot = $this->snapshotManager->getLatestSnapshot();
                if ($snapshot) {
                    $fallbackResult = $this->agentClient->requestFallbackHtml(
                        context: $context,
                        homepageHtml: $snapshot->getFullHtml() ?? '',
                        homepageCss: $snapshot->getInlinedCss() ?? '',
                        timeout: $this->config->getFallbackTimeout()
                    );

                    if ($fallbackResult->hasHtml()) {
                        $response = $this->fallbackBuilder->build(
                            html: $fallbackResult->getHtml(),
                            statusCode: $this->config->getResponseHttpStatus()
                        );
                        $attempt->setOutcome('fallback_html');
                        $attempt->setFallbackHtmlReturned($fallbackResult->getHtml());
                        $attempt->setOutcomeDetail('Served AI-generated fallback page');
                        $attempt->setStatus('completed');
                        return $response;
                    }
                }
            }

            // 6c. Nothing worked
            $attempt->setOutcome('failed');
            $attempt->setOutcomeDetail($healResult->getFailureReason() ?? 'Healing unsuccessful');
            $attempt->setStatus('failed');
            return null;

        } catch (\Throwable $agentException) {
            $attempt->setOutcome('error');
            $attempt->setOutcomeDetail($agentException->getMessage());
            $attempt->setStatus('failed');
            $this->logger->error('[AiSelfHealing] Agent error: ' . $agentException->getMessage());
            return null;

        } finally {
            $duration = microtime(true) - $startTime;
            $attempt->setAgentDurationSeconds($duration);
            $attempt->setExecutionTimeMs((int) ($duration * 1000));
            try {
                $this->attemptRepository->save($attempt);
            } catch (\Throwable $saveError) {
                $this->logger->error('[AiSelfHealing] Failed to save attempt: ' . $saveError->getMessage());
            }
            $this->concurrencyGuard->release();
        }
    }
}
