<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Backend model that converts the snapshot frequency dropdown value
 * into an actual cron expression and stores it in the cron_expression config path.
 */
class SnapshotCron extends Value
{
    private const CRON_EXPRESSION_PATH = 'ai_self_healing/snapshot/cron_expression';

    private const FREQUENCY_TO_CRON = [
        'every_hour' => '0 * * * *',
        'every_6h'   => '0 */6 * * *',
        'daily'      => '0 2 * * *',
        'manual'     => '',
    ];

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ValueFactory $configValueFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * After saving the frequency dropdown, write the corresponding cron expression.
     */
    public function afterSave(): static
    {
        $frequency = $this->getValue();
        $cronExpression = self::FREQUENCY_TO_CRON[$frequency] ?? '';

        // Check if a custom expression was explicitly provided
        $customExpression = $this->getData('groups/snapshot/fields/cron_expression/value');
        if (!empty($customExpression) && $frequency === 'custom') {
            $cronExpression = $customExpression;
        }

        try {
            $this->configValueFactory->create()->load(
                self::CRON_EXPRESSION_PATH,
                'path'
            )->setValue(
                $cronExpression
            )->setPath(
                self::CRON_EXPRESSION_PATH
            )->save();
        } catch (\Exception $e) {
            throw new \RuntimeException(__('Unable to save the cron expression.'));
        }

        return parent::afterSave();
    }
}
