<?php

declare(strict_types=1);

namespace Myca\Core\Setup\Patch\Data;

use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Acl\AclResource\ProviderInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Rôles d'administration séparés (cahier des charges : support, logistique, finance, catalogue ; l'administrateur
 * garde le rôle « Administrators »). Chaque droit accordé vaut pour ses sous-droits, sauf s'il est préfixé de « = »
 * (droit seul, par exemple consulter sans agir) ; ses parents sont ajoutés pour l'accès aux menus. Un rôle existant du même nom n'est pas modifié (ajustements possibles dans Système > Rôles).
 */
class CreateAdminRoles implements DataPatchInterface
{
    public const ROLES = [
        'MYCA – Support client' => [
            'Magento_Backend::dashboard',
            'Magento_Sales::actions_view', 'Magento_Sales::comment', 'Magento_Sales::emails',
            'Magento_Customer::manage',
            'Myca_Rma::manage',
            'Myca_Quote::quote',
            'Myca_Passport::verify',
            'Myca_Loyalty::loyalty',
            '=Myca_Partner::partners', 'Myca_Partner::applications',
        ],
        'MYCA – Logistique' => [
            'Magento_Backend::dashboard',
            'Magento_Sales::actions_view', 'Magento_Sales::comment', 'Magento_Sales::ship', 'Magento_Sales::shipment',
            'Magento_Sales::hold',
            'Magento_InventoryApi::inventory',
            '=Myca_Production::production', 'Myca_Production::orders', 'Myca_Production::quality',
            'Myca_Rma::manage',
        ],
        'MYCA – Finance' => [
            'Magento_Backend::dashboard',
            'Magento_Sales::actions_view', 'Magento_Sales::invoice', 'Magento_Sales::sales_invoice',
            'Magento_Sales::creditmemo', 'Magento_Sales::sales_creditmemo', 'Magento_Sales::transactions',
            'Magento_Sales::cancel',
            'Myca_Rma::refund',
            '=Myca_Partner::partners',
            'Myca_Loyalty::loyalty',
            'Magento_Reports::report',
        ],
        'MYCA – Catalogue' => [
            'Magento_Backend::dashboard',
            'Magento_Catalog::catalog_inventory',
            'Magento_Backend::content',
            'Magento_Review::reviews_all',
            'Myca_Passport::passport',
            'Myca_Production::techpacks',
        ],
    ];

    public function __construct(
        private readonly RoleFactory $roleFactory,
        private readonly RulesFactory $rulesFactory,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly ProviderInterface $aclResourceProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        [$parents, $children] = $this->indexTree($this->aclResourceProvider->getAclResources());
        foreach (self::ROLES as $name => $granted) {
            $exists = $this->roleCollectionFactory->create()
                ->addFieldToFilter('role_name', $name)
                ->addFieldToFilter('role_type', RoleGroup::ROLE_TYPE)
                ->getSize();
            if ($exists) {
                continue;
            }
            $resources = [];
            foreach ($granted as $id) {
                $exact = str_starts_with($id, '=');
                $id = ltrim($id, '=');
                if (!isset($parents[$id])) {
                    $this->logger->warning(sprintf('Myca_Core : droit ACL inconnu ignoré pour le rôle « %s » : %s', $name, $id));
                    continue;
                }
                $resources = array_merge(
                    $resources,
                    [$id],
                    $exact ? [] : $this->descendants($id, $children),
                    $this->ancestors($id, $parents)
                );
            }
            $role = $this->roleFactory->create();
            $role->setRoleName($name)
                ->setUserType((string) UserContextInterface::USER_TYPE_ADMIN)
                ->setUserId(0)
                ->setRoleType(RoleGroup::ROLE_TYPE)
                ->setSortOrder(0)
                ->setTreeLevel(1)
                ->setParentId(0)
                ->save();
            $rules = $this->rulesFactory->create();
            $rules->setData('role_id', (int) $role->getId());
            $rules->setData('resources', array_values(array_unique($resources)));
            $rules->saveRel();
        }

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     * @param array<string, string|null> $parents
     * @param array<string, string[]> $children
     * @return array{0: array<string, string|null>, 1: array<string, string[]>}
     */
    private function indexTree(array $tree, ?string $parent = null, array $parents = [], array $children = []): array
    {
        foreach ($tree as $node) {
            $id = (string) $node['id'];
            $parents[$id] = $parent;
            if ($parent !== null) {
                $children[$parent][] = $id;
            }
            [$parents, $children] = $this->indexTree((array) ($node['children'] ?? []), $id, $parents, $children);
        }

        return [$parents, $children];
    }

    /**
     * @param array<string, string[]> $children
     * @return string[]
     */
    private function descendants(string $id, array $children): array
    {
        $all = [];
        foreach ($children[$id] ?? [] as $child) {
            $all = array_merge($all, [$child], $this->descendants($child, $children));
        }

        return $all;
    }

    /**
     * @param array<string, string|null> $parents
     * @return string[]
     */
    private function ancestors(string $id, array $parents): array
    {
        $all = [];
        while (($id = $parents[$id] ?? null) !== null) {
            $all[] = $id;
        }

        return $all;
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
