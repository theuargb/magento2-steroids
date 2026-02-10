<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SnapshotFrequency implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'every_hour', 'label' => __('Every Hour')],
            ['value' => 'every_6h', 'label' => __('Every 6 Hours')],
            ['value' => 'daily', 'label' => __('Daily')],
            ['value' => 'manual', 'label' => __('Manual Only')],
        ];
    }
}
