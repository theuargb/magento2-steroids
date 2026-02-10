<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * "Capture Snapshot Now" button in system config.
 */
class CaptureButton extends Field
{
    protected $_template = 'Theuargb_AiSelfHealing::system/config/capture_button.phtml';

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('aiselfhealing/snapshot/capture');
    }

    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'aiselfhealing_capture_snapshot',
            'label' => __('Capture Snapshot Now'),
        ]);

        return $button->toHtml();
    }
}
