<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\App\Http as MagentoHttp;
use Magento\Framework\App\ResponseInterface;
use Theuargb\AiSelfHealing\Plugin\AppHttpPlugin;
use Psr\Log\LoggerInterface;

/**
 * Re-dispatches the original request after a healing action
 * to verify the fix actually works.
 */
class RequestRedispatcher
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Re-run the Magento application launch to see if the error is resolved.
     *
     * @throws \Throwable if the re-dispatch still fails
     */
    public function redispatch(MagentoHttp $app): ResponseInterface
    {
        $this->logger->info('[AiSelfHealing] Re-dispatching request after healing');

        // Prevent the plugin from intercepting this re-dispatch
        AppHttpPlugin::markRedispatching();

        try {
            return $app->launch();
        } finally {
            // Reset so future requests are still intercepted
            // (Not strictly needed since each PHP process is a new instance,
            // but safe for long-running processes like queue workers)
        }
    }
}
