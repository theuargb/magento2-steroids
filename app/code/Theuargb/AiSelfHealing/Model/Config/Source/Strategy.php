<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Strategy implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'all', 'label' => __('All URLs')],
            ['value' => 'patterns', 'label' => __('Specific Patterns')],
            ['value' => 'none', 'label' => __('None')],
        ];
    }
}
