<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;

/**
 * "Scrape Branding" button in admin config.
 * Makes an AJAX call to the Firecrawl API via admin controller,
 * then populates the Design JSON textarea with the result.
 */
class ScrapeBranding extends Field
{
    protected $_template = 'Theuargb_Steroids::system/config/scrape-branding.phtml';

    /**
     * Remove scope label and use full row for button.
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * Render without label column — the button takes the full row.
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the AJAX URL for the scrape controller.
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('steroids/branding/scrape');
    }
}
