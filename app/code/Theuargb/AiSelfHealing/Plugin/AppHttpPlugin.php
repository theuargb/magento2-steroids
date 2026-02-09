<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Plugin;

use Magento\Framework\App\Http;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Theuargb\AiSelfHealing\Api\AgentClientInterface;
use Theuargb\AiSelfHealing\Api\HealingAttemptRepositoryInterface;
use Theuargb\AiSelfHealing\Api\Data\HealingAttemptInterfaceFactory;
use Theuargb\AiSelfHealing\Helper\Config;
use Theuargb\AiSelfHealing\Helper\ContextCollector;
use Theuargb\AiSelfHealing\Helper\UrlFilter;

class AppHttpPlugin
{
    private Config $config;
    private UrlFilter $urlFilter;
    private ContextCollector $contextCollector;
    private AgentClientInterface $agentClient;
    private HealingAttemptRepositoryInterface $healingAttemptRepository;
    private HealingAttemptInterfaceFactory $healingAttemptFactory;
    private Json $json;
    private LoggerInterface $logger;
    private HttpResponse $response;

    public function __construct(
        Config $config,
        UrlFilter $urlFilter,
        ContextCollector $contextCollector,
        AgentClientInterface $agentClient,
        HealingAttemptRepositoryInterface $healingAttemptRepository,
        HealingAttemptInterfaceFactory $healingAttemptFactory,
        Json $json,
        LoggerInterface $logger,
        HttpResponse $response
    ) {
        $this->config = $config;
        $this->urlFilter = $urlFilter;
        $this->contextCollector = $contextCollector;
        $this->agentClient = $agentClient;
        $this->healingAttemptRepository = $healingAttemptRepository;
        $this->healingAttemptFactory = $healingAttemptFactory;
        $this->json = $json;
        $this->logger = $logger;
        $this->response = $response;
    }

    public function aroundLaunch(Http $subject, callable $proceed)
    {
        if (!$this->config->isEnabled()) {
            return $proceed();
        }

        try {
            return $proceed();
        } catch (\Throwable $exception) {
            return $this->handleException($subject, $exception);
        }
    }

    private function handleException(Http $subject, \Throwable $exception)
    {
        $request = $subject->getRequest();
        
        // Check if we should intercept this URL
        if (!$this->urlFilter->shouldIntercept($request)) {
            throw $exception;
        }

        $fingerprint = $this->contextCollector->generateFingerprint($exception);
        
        // Check rate limiting
        $recentAttempts = $this->healingAttemptRepository->countRecentAttemptsByFingerprint(
            $fingerprint,
            1
        );
        
        if ($recentAttempts >= $this->config->getMaxAttemptsPerHour()) {
            $this->logger->info('Rate limit exceeded for fingerprint: ' . $fingerprint);
            throw $exception;
        }

        // Collect context
        $context = $this->contextCollector->collect($request, $exception);
        
        // Create healing attempt record
        $healingAttempt = $this->healingAttemptFactory->create();
        $healingAttempt->setFingerprint($fingerprint);
        $healingAttempt->setUrl($request->getRequestUri());
        $healingAttempt->setErrorMessage($exception->getMessage());
        $healingAttempt->setErrorTrace($exception->getTraceAsString());
        $healingAttempt->setContextSnapshot($this->json->serialize($context));
        $healingAttempt->setStatus('pending');
        
        $startTime = microtime(true);
        
        $mode = $this->config->getMode();
        
        if ($mode === 'monitor_only') {
            $healingAttempt->setStatus('monitored');
            $healingAttempt->setOutcome('not_attempted');
            $this->healingAttemptRepository->save($healingAttempt);
            throw $exception;
        }

        try {
            // Call AI agent
            $healingAttempt->setAgentRequest($this->json->serialize($context));
            $agentResponse = $this->agentClient->heal($context);
            $healingAttempt->setAgentResponse($this->json->serialize($agentResponse));
            
            $executionTime = (int) ((microtime(true) - $startTime) * 1000);
            $healingAttempt->setExecutionTimeMs($executionTime);

            if (!empty($agentResponse['success'])) {
                $healingAttempt->setStatus('completed');
                $healingAttempt->setOutcome('healed');
                $this->healingAttemptRepository->save($healingAttempt);
                
                // Return rewritten response if enabled
                if ($this->urlFilter->canRewriteResponse($request->getRequestUri()) 
                    && !empty($agentResponse['html'])) {
                    $this->response->setHttpResponseCode($this->config->getResponseHttpStatus());
                    $this->response->setBody($agentResponse['html']);
                    return $this->response;
                }
            } else {
                $healingAttempt->setStatus('failed');
                $healingAttempt->setOutcome('agent_error');
                $this->healingAttemptRepository->save($healingAttempt);
            }
        } catch (\Exception $e) {
            $executionTime = (int) ((microtime(true) - $startTime) * 1000);
            $healingAttempt->setExecutionTimeMs($executionTime);
            $healingAttempt->setStatus('failed');
            $healingAttempt->setOutcome('exception');
            $this->healingAttemptRepository->save($healingAttempt);
            $this->logger->error('Healing attempt failed: ' . $e->getMessage());
        }

        // Re-throw original exception
        throw $exception;
    }
}
