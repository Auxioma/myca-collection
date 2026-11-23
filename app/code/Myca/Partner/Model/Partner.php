<?php

declare(strict_types=1);

namespace Myca\Partner\Model;

use Magento\Framework\Model\AbstractModel;
use Myca\Partner\Api\Data\PartnerInterface;

class Partner extends AbstractModel implements PartnerInterface
{
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_REJECTED];

    /** @var string */
    protected $_eventPrefix = 'myca_partner';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Partner::class);
    }

    public function getCode(): string
    {
        return (string) $this->getData('code');
    }

    public function setCode(string $code): self
    {
        return $this->setData('code', $code);
    }

    public function getCompanyName(): string
    {
        return (string) $this->getData('company_name');
    }

    public function setCompanyName(string $name): self
    {
        return $this->setData('company_name', $name);
    }

    public function getStatus(): string
    {
        return (string) ($this->getData('status') ?: self::STATUS_PENDING);
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Statut partenaire inconnu : %s', $status));
        }

        return $this->setData('status', $status);
    }

    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }

    public function getCustomerGroupId(): ?int
    {
        $value = $this->getData('customer_group_id');

        return $value === null ? null : (int) $value;
    }

    public function setCustomerGroupId(?int $groupId): self
    {
        return $this->setData('customer_group_id', $groupId);
    }

    public function getSalesModes(): array
    {
        $value = (string) $this->getData('sales_modes');

        return $value === '' ? [] : array_values(array_intersect(
            explode(',', $value),
            [self::MODE_DROPSHIP, self::MODE_STOCK]
        ));
    }

    public function setSalesModes(array $modes): self
    {
        return $this->setData('sales_modes', implode(',', array_intersect([self::MODE_DROPSHIP, self::MODE_STOCK], $modes)));
    }

    public function getLogo(): ?string
    {
        $value = (string) $this->getData('logo');

        return $value === '' ? null : $value;
    }
}
