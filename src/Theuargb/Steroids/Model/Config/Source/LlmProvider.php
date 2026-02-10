<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LlmProvider implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'openai', 'label' => __('OpenAI')],
            ['value' => 'openai_realtime', 'label' => __('OpenAI Realtime (WebSocket)')],
            ['value' => 'anthropic', 'label' => __('Anthropic')],
            ['value' => 'openai_compatible', 'label' => __('OpenAI-Compatible (custom base URL)')],
        ];
    }
}
