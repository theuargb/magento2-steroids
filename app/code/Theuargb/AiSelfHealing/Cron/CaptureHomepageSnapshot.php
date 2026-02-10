<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Cron;

use Psr\Log\LoggerInterface;
use Theuargb\AiSelfHealing\Helper\Config;
use Theuargb\AiSelfHealing\Model\HomepageSnapshotManager;

class CaptureHomepageSnapshot
{
    public function __construct(
        private readonly Config $config,
        private readonly HomepageSnapshotManager $snapshotManager,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->logger->info('[AiSelfHealing] Cron: capturing homepage snapshot');
        $this->snapshotManager->capture();
    }
}
