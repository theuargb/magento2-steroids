<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'monitor_only', 'label' => __('Monitor Only')],
            ['value' => 'auto_heal', 'label' => __('Auto Heal')],
        ];
    }
}
