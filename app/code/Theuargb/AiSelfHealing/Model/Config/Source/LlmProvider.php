<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LlmProvider implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'openai', 'label' => __('OpenAI')],
            ['value' => 'anthropic', 'label' => __('Anthropic')],
            ['value' => 'openai_compatible', 'label' => __('OpenAI-Compatible (custom base URL)')],
        ];
    }
}
