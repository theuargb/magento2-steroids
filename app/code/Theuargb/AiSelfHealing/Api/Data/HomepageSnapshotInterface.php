<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Api\Data;

interface HomepageSnapshotInterface
{
    public const ENTITY_ID = 'entity_id';
    public const STORE_ID = 'store_id';
    public const BASE_URL = 'base_url';
    public const FULL_HTML = 'full_html';
    public const INLINED_CSS = 'inlined_css';
    public const HTTP_STATUS_CODE = 'http_status_code';
    public const CAPTURED_AT = 'captured_at';

    /** @return int|null */
    public function getEntityId(): ?int;
    /** @param int $entityId @return $this */
    public function setEntityId($entityId): self;

    /** @return int */
    public function getStoreId(): int;
    /** @param int $storeId @return $this */
    public function setStoreId(int $storeId): self;

    /** @return string|null */
    public function getBaseUrl(): ?string;
    /** @param string $url @return $this */
    public function setBaseUrl(string $url): self;

    /** @return string|null */
    public function getFullHtml(): ?string;
    /** @param string|null $html @return $this */
    public function setFullHtml(?string $html): self;

    /** @return string|null */
    public function getInlinedCss(): ?string;
    /** @param string|null $css @return $this */
    public function setInlinedCss(?string $css): self;

    /** @return int|null */
    public function getHttpStatusCode(): ?int;
    /** @param int|null $code @return $this */
    public function setHttpStatusCode(?int $code): self;

    /** @return string|null */
    public function getCapturedAt(): ?string;
    /** @param string $capturedAt @return $this */
    public function setCapturedAt(string $capturedAt): self;
}
