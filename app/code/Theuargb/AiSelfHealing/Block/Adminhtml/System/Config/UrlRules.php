<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

/**
 * Dynamic rows for URL rules with per-URL prompt configuration.
 * Each row: URL pattern | Custom AI prompt | Allow response rewrite | Enabled
 */
class UrlRules extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn('pattern', [
            'label' => __('URL Pattern'),
            'class' => 'required-entry',
            'style' => 'width:200px',
        ]);

        $this->addColumn('prompt', [
            'label' => __('Custom AI Prompt'),
            'style' => 'width:350px',
        ]);

        $this->addColumn('response_rewrite', [
            'label' => __('Allow Fallback HTML'),
            'style' => 'width:60px',
        ]);

        $this->addColumn('enabled', [
            'label' => __('Enabled'),
            'style' => 'width:60px',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add URL Rule');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        // No custom option hash needed for simple text columns
    }
}
