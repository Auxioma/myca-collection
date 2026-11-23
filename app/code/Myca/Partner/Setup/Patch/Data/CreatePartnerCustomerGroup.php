<?php

declare(strict_types=1);

namespace Myca\Partner\Setup\Patch\Data;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Tax\Model\ClassModel;
use Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory as TaxClassCollectionFactory;

/**
 * Groupe client « Partenaire MYCA » : il donne l'identité partenaire (Myca_Core) et portera les tarifs
 * partenaires. Il est déclaré dans myca_core/identity/partner_groups.
 */
class CreatePartnerCustomerGroup implements DataPatchInterface
{
    public const GROUP_CODE = 'Partenaire MYCA';

    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly GroupInterfaceFactory $groupFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly TaxClassCollectionFactory $taxClassCollectionFactory,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        $existing = $this->groupRepository->getList(
            $this->searchCriteriaBuilder->addFilter('customer_group_code', self::GROUP_CODE)->create()
        )->getItems();
        /** @var GroupInterface|null $group */
        $group = reset($existing) ?: null;
        if ($group === null) {
            $group = $this->groupFactory->create();
            $group->setCode(self::GROUP_CODE);
            $group->setTaxClassId($this->getCustomerTaxClassId());
            $group = $this->groupRepository->save($group);
        }
        $this->configWriter->save('myca_core/identity/partner_groups', (string) $group->getId());

        return $this;
    }

    private function getCustomerTaxClassId(): int
    {
        $taxClass = $this->taxClassCollectionFactory->create()
            ->addFieldToFilter('class_type', ClassModel::TAX_CLASS_TYPE_CUSTOMER)
            ->setOrder('class_id', 'asc')
            ->getFirstItem();

        return (int) $taxClass->getData('class_id') ?: 3;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
