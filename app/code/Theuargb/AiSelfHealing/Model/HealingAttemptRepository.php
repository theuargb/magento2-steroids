<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Theuargb\AiSelfHealing\Api\Data\HealingAttemptInterface;
use Theuargb\AiSelfHealing\Api\HealingAttemptRepositoryInterface;
use Theuargb\AiSelfHealing\Model\ResourceModel\HealingAttempt as HealingAttemptResource;
use Theuargb\AiSelfHealing\Model\ResourceModel\HealingAttempt\CollectionFactory;

class HealingAttemptRepository implements HealingAttemptRepositoryInterface
{
    private HealingAttemptResource $resource;
    private HealingAttemptFactory $healingAttemptFactory;
    private CollectionFactory $collectionFactory;

    public function __construct(
        HealingAttemptResource $resource,
        HealingAttemptFactory $healingAttemptFactory,
        CollectionFactory $collectionFactory
    ) {
        $this->resource = $resource;
        $this->healingAttemptFactory = $healingAttemptFactory;
        $this->collectionFactory = $collectionFactory;
    }

    public function save(HealingAttemptInterface $healingAttempt): HealingAttemptInterface
    {
        try {
            $this->resource->save($healingAttempt);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $healingAttempt;
    }

    public function getById($entityId): HealingAttemptInterface
    {
        $healingAttempt = $this->healingAttemptFactory->create();
        $this->resource->load($healingAttempt, $entityId);
        if (!$healingAttempt->getEntityId()) {
            throw new NoSuchEntityException(__('Healing attempt with id "%1" does not exist.', $entityId));
        }
        return $healingAttempt;
    }

    public function delete(HealingAttemptInterface $healingAttempt): bool
    {
        try {
            $this->resource->delete($healingAttempt);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return true;
    }

    public function countRecentAttemptsByFingerprint(string $fingerprint, int $withinHours = 1): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('fingerprint', $fingerprint);
        
        $datetime = new \DateTime();
        $datetime->modify("-{$withinHours} hours");
        $collection->addFieldToFilter('created_at', ['gteq' => $datetime->format('Y-m-d H:i:s')]);
        
        return $collection->getSize();
    }
}
