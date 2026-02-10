<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Controller\Adminhtml\Snapshot;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Theuargb\AiSelfHealing\Model\HomepageSnapshotManager;

/**
 * Admin AJAX endpoint: capture homepage snapshot on demand.
 */
class Capture extends Action
{
    public const ADMIN_RESOURCE = 'Theuargb_AiSelfHealing::config';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly HomepageSnapshotManager $snapshotManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $snapshot = $this->snapshotManager->capture();
            if ($snapshot) {
                return $result->setData([
                    'success' => true,
                    'message' => __('Homepage snapshot captured successfully.'),
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => __('Failed to capture homepage snapshot. Check logs for details.'),
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error: %1', $e->getMessage()),
            ]);
        }
    }
}
