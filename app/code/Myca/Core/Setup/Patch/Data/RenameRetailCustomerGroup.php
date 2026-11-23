<?php

declare(strict_types=1);

namespace Myca\Core\Setup\Patch\Data;

use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Le groupe client par défaut (« General ») devient « Client MYCA », libellé affiché dans l'espace client (M28).
 * Les paliers (ex. « Client Privilège ») seront des groupes supplémentaires, à valider.
 */
class RenameRetailCustomerGroup implements DataPatchInterface
{
    public function __construct(
        private readonly GroupManagementInterface $groupManagement,
        private readonly GroupRepositoryInterface $groupRepository
    ) {
    }

    public function apply(): self
    {
        $group = $this->groupManagement->getDefaultGroup();
        $group->setCode('Client MYCA');
        $this->groupRepository->save($group);

        return $this;
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
