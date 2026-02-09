<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model\ResourceModel\HealingAttempt;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Theuargb\AiSelfHealing\Model\HealingAttempt as HealingAttemptModel;
use Theuargb\AiSelfHealing\Model\ResourceModel\HealingAttempt as HealingAttemptResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(HealingAttemptModel::class, HealingAttemptResource::class);
    }
}
