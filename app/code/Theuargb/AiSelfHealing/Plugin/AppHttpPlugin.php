<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Plugin;

use Magento\Framework\App\Http as MagentoHttp;
use Magento\Framework\App\ResponseInterface;
use Theuargb\AiSelfHealing\Model\HealingOrchestrator;
use Theuargb\AiSelfHealing\Helper\Config;
use Psr\Log\LoggerInterface;

class AppHttpPlugin
{
    /**
     * Reentrance flag to prevent recursive healing when re-dispatching.
     */
    private static bool $isRedispatching = false;

    public function __construct(
        private readonly HealingOrchestrator $orchestrator,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Mark that we are re-dispatching — skip healing on the next launch.
     */
    public static function markRedispatching(): void
    {
        self::$isRedispatching = true;
    }

    /**
     * Around plugin on the main application launch.
     * Wraps Magento's entire request dispatch cycle including routing,
     * controller dispatch, layout rendering, and response sending.
     */
    public function aroundLaunch(
        MagentoHttp $subject,
        callable $proceed
    ): ResponseInterface {
        if (!$this->config->isEnabled() || self::$isRedispatching) {
            return $proceed();
        }

        try {
            return $proceed();
        } catch (\Throwable $e) {
            try {
                $result = $this->orchestrator->handle($e, $subject);
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $orchestratorException) {
                // The healer itself failed — never let it make things worse
                $this->logger->critical(
                    '[AiSelfHealing] Orchestrator failure, passing through original exception',
                    [
                        'original_exception' => $e->getMessage(),
                        'orchestrator_exception' => $orchestratorException->getMessage(),
                    ]
                );
            }

            // Could not heal — rethrow original so Magento's default
            // error handling takes over (error page, report, etc.)
            throw $e;
        }
    }
}
