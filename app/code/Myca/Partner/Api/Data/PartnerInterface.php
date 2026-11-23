<?php

declare(strict_types=1);

namespace Myca\Partner\Api\Data;

/**
 * Partenaire B2B (boutique). Les commandes, factures et clients finaux lui sont rattachés par partner_id.
 */
interface PartnerInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REJECTED = 'rejected';

    public const MODE_DROPSHIP = 'dropship';
    public const MODE_STOCK = 'stock';

    /**
     * @return int|string|null
     */
    public function getId();

    public function getCode(): string;

    public function setCode(string $code): self;

    public function getCompanyName(): string;

    public function setCompanyName(string $name): self;

    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function isActive(): bool;

    public function getCustomerGroupId(): ?int;

    public function setCustomerGroupId(?int $groupId): self;

    /**
     * @return string[] dropship, stock
     */
    public function getSalesModes(): array;

    /**
     * @param string[] $modes
     */
    public function setSalesModes(array $modes): self;

    public function getLogo(): ?string;
}
