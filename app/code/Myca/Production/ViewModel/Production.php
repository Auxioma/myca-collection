<?php

declare(strict_types=1);

namespace Myca\Production\ViewModel;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Production\Model\ProductionManagement;
use Myca\Production\Model\ProductionRules;
use Myca\Production\Model\Source\Status;
use Myca\Production\Model\TechpackManagement;

/**
 * Écrans d'administration de la production (M10, M11).
 */
class Production implements ArgumentInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ProductionManagement $management,
        private readonly ProductionRules $rules,
        private readonly TechpackManagement $techpackManagement,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ResourceConnection $resource,
        private readonly Json $json,
        private readonly UrlInterface $url
    ) {
    }

    public function getManagement(): ProductionManagement
    {
        return $this->management;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrder(): ?array
    {
        $orderId = (int) $this->request->getParam('id');

        return $orderId > 0 ? $this->management->getOrder($orderId) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSupplier(): ?array
    {
        $supplierId = (int) $this->request->getParam('id');

        return $supplierId > 0 ? $this->management->getSupplier($supplierId) : null;
    }

    /**
     * Indicateurs de M10, calculés sur les ordres non annulés.
     *
     * @return array{orders: int, planned: int, in_production: int, received: int, quarantine: int, next_delivery: ?string}
     */
    public function getSummary(): array
    {
        $connection = $this->resource->getConnection();
        $orders = $this->resource->getTableName('myca_production_order');
        $lines = $this->resource->getTableName('myca_production_line');
        $row = $connection->fetchRow(
            $connection->select()
                ->from(['o' => $orders], [
                    'orders' => new \Zend_Db_Expr('COUNT(DISTINCT o.order_id)'),
                    'planned' => new \Zend_Db_Expr('COALESCE(SUM(l.qty_planned), 0)'),
                    'in_production' => new \Zend_Db_Expr("COALESCE(SUM(IF(o.status IN ('committed', 'in_production', 'receiving'), GREATEST(l.qty_planned - l.qty_received, 0), 0)), 0)"),
                    'received' => new \Zend_Db_Expr('COALESCE(SUM(l.qty_accepted), 0)'),
                    'quarantine' => new \Zend_Db_Expr('COALESCE(SUM(l.qty_quarantine), 0)'),
                ])
                ->joinLeft(['l' => $lines], 'l.order_id = o.order_id', [])
                ->where('o.status <> ?', ProductionRules::CANCELED)
        );
        $next = $connection->fetchOne(
            $connection->select()->from($orders, ['MIN(planned_delivery)'])
                ->where('status IN (?)', [ProductionRules::COMMITTED, ProductionRules::IN_PRODUCTION, ProductionRules::RECEIVING])
                ->where('planned_delivery >= CURDATE()')
        );

        return [
            'orders' => (int) ($row['orders'] ?? 0),
            'planned' => (int) ($row['planned'] ?? 0),
            'in_production' => (int) ($row['in_production'] ?? 0),
            'received' => (int) ($row['received'] ?? 0),
            'quarantine' => (int) ($row['quarantine'] ?? 0),
            'next_delivery' => is_string($next) && $next !== '' ? $next : null,
        ];
    }

    /**
     * @return array<int, string> modèles (produits configurables et simples visibles) pour un nouvel ordre ou une fiche
     */
    public function getModelOptions(): array
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('type_id', ['in' => ['configurable', 'simple']])
            ->addAttributeToFilter('visibility', ['neq' => 1])
            ->setOrder('name', 'ASC');
        $options = [];
        foreach ($collection as $product) {
            $options[(int) $product->getId()] = (string) $product->getName() . ' (' . $product->getSku() . ')';
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function getSupplierOptions(): array
    {
        return $this->management->getSupplierOptions(true);
    }

    public function getStatusLabel(string $status): string
    {
        return Status::getLabels()[$status] ?? $status;
    }

    /**
     * @param array<string, mixed> $order
     * @return string[]
     */
    public function getCommitBlockers(array $order): array
    {
        return $this->rules->getCommitBlockers($order, $this->management->getLines((int) $order['order_id']));
    }

    /**
     * @param array<string, mixed> $order
     * @return array{factory_total: float, extra: float, accepted: int, landed_unit: ?float}
     */
    public function getCosts(array $order): array
    {
        $planned = 0;
        $accepted = 0;
        foreach ($this->management->getLines((int) $order['order_id']) as $line) {
            $planned += (int) $line['qty_planned'];
            $accepted += (int) $line['qty_accepted'];
        }

        return [
            'factory_total' => round((float) $order['unit_factory_price'] * $planned, 2),
            'extra' => (float) $order['extra_costs'],
            'accepted' => $accepted,
            'landed_unit' => $this->rules->getLandedUnitCost((float) $order['unit_factory_price'], (float) $order['extra_costs'], $accepted),
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, int> couleurs sous le MOQ
     */
    public function getMoqShortfalls(array $order): array
    {
        return $this->rules->getMoqShortfalls($this->management->getLines((int) $order['order_id']), (int) $order['moq_per_color']);
    }

    public function isEditable(string $status): bool
    {
        return $this->rules->isEditable($status);
    }

    public function canReceive(string $status): bool
    {
        return $this->rules->canReceive($status);
    }

    /**
     * @return array<string, string>
     */
    public function getMilestoneLabels(): array
    {
        return [
            'sample' => (string) __('Échantillon'),
            'pattern' => (string) __('Validation du patronage'),
            'embroidery' => (string) __('Broderie'),
            'colors' => (string) __('Couleurs'),
            'quality' => (string) __('Qualité (pré-série)'),
            'launch' => (string) __('Lancement industriel'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getChecklistLabels(): array
    {
        return [
            'material' => (string) __('Matière'),
            'color' => (string) __('Couleur'),
            'dimensions' => (string) __('Dimensions (dans la tolérance)'),
            'logos' => (string) __('Logos'),
            'embroidery' => (string) __('Broderies'),
            'labels' => (string) __('Étiquettes'),
            'packaging' => (string) __('Emballages'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getDecisionLabels(): array
    {
        return [
            ProductionRules::ACCEPTED => (string) __('Accepté (entre en stock vendable)'),
            ProductionRules::QUARANTINE => (string) __('Quarantaine (vente bloquée)'),
            ProductionRules::REFUSED => (string) __('Refusé'),
        ];
    }

    /**
     * @param array<string, mixed> $receipt
     * @return array<string, string>
     */
    public function getChecklist(array $receipt): array
    {
        $checklist = $this->json->unserialize((string) $receipt['checklist']);

        return is_array($checklist) ? array_map('strval', $checklist) : [];
    }

    public function getSelectedProductId(): int
    {
        return (int) $this->request->getParam('product_id');
    }

    /**
     * @return array<string, string>
     */
    public function getTechpackForm(int $productId): array
    {
        return $productId > 0 ? $this->techpackManagement->getForm($productId) : [];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getUrl(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }
}
