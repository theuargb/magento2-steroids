<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\Model\AbstractModel;
use Theuargb\AiSelfHealing\Api\Data\HomepageSnapshotInterface;

class HomepageSnapshot extends AbstractModel implements HomepageSnapshotInterface
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\HomepageSnapshot::class);
    }

    public function getEntityId(): ?int
    {
        $v = $this->getData(self::ENTITY_ID);
        return $v !== null ? (int) $v : null;
    }

    public function setEntityId($entityId): HomepageSnapshotInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): HomepageSnapshotInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getBaseUrl(): ?string
    {
        return $this->getData(self::BASE_URL);
    }

    public function setBaseUrl(string $url): HomepageSnapshotInterface
    {
        return $this->setData(self::BASE_URL, $url);
    }

    public function getFullHtml(): ?string
    {
        return $this->getData(self::FULL_HTML);
    }

    public function setFullHtml(?string $html): HomepageSnapshotInterface
    {
        return $this->setData(self::FULL_HTML, $html);
    }

    public function getInlinedCss(): ?string
    {
        return $this->getData(self::INLINED_CSS);
    }

    public function setInlinedCss(?string $css): HomepageSnapshotInterface
    {
        return $this->setData(self::INLINED_CSS, $css);
    }

    public function getHttpStatusCode(): ?int
    {
        $v = $this->getData(self::HTTP_STATUS_CODE);
        return $v !== null ? (int) $v : null;
    }

    public function setHttpStatusCode(?int $code): HomepageSnapshotInterface
    {
        return $this->setData(self::HTTP_STATUS_CODE, $code);
    }

    public function getCapturedAt(): ?string
    {
        return $this->getData(self::CAPTURED_AT);
    }

    public function setCapturedAt(string $capturedAt): HomepageSnapshotInterface
    {
        return $this->setData(self::CAPTURED_AT, $capturedAt);
    }
}
