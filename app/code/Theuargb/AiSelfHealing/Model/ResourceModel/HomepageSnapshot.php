<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class HomepageSnapshot extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('aiselfhealing_homepage_snapshot', 'entity_id');
    }
}
