<?php

declare(strict_types=1);

namespace Myca\PartnerSales\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Myca\Dropship\Model\ResourceModel\DropshipOrder;
use Myca\Partner\Model\PartnerData;

/**
 * Lignes de vente d'une boutique (commandes en dropshipping uniquement : les ventes en boutique physique ne sont pas
 * connues de MYCA). Le prix de vente d'une ligne vient, par ordre de priorité :
 * 1. du montant HT déclaré sur la commande, réparti au prorata de l'achat MYCA de chaque ligne ;
 * 2. du prix de vente HT saisi par la boutique pour le modèle ;
 * 3. si l'option est activée, du prix public conseillé HT (estimation signalée) ;
 * sinon il reste inconnu, et la ligne est exclue des marges.
 */
class SalesData
{
    public const SOURCE_ORDER = 'order';
    public const SOURCE_PRICE_LIST = 'price_list';
    public const SOURCE_PUBLIC = 'public';

    /** @var array<int, array<int, array<string, mixed>>> */
    private array $lines = [];

    /** @var array<int, string> */
    private array $names = [];

    public function __construct(
        private readonly PartnerData $partnerData,
        private readonly DropshipOrder $dropshipOrder,
        private readonly MarginCalculator $calculator,
        private readonly ResourceConnection $resource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLines(int $partnerId): array
    {
        if (isset($this->lines[$partnerId])) {
            return $this->lines[$partnerId];
        }
        $declarations = $this->dropshipOrder->getForPartner($partnerId);
        $prices = $this->getPrices($partnerId);
        $publicFallback = $this->scopeConfig->isSetFlag('myca_partner_sales/margin/public_price_fallback');
        $lines = [];
        foreach ($this->partnerData->getOrders($partnerId) as $order) {
            $orderId = (int) $order->getId();
            if ($order->getState() === Order::STATE_CANCELED || ($declarations[$orderId]['mode'] ?? null) !== DropshipOrder::MODE_DROPSHIP) {
                continue;
            }
            $items = [];
            $costs = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $itemId = (int) $item->getItemId();
                $items[$itemId] = $item;
                $costs[$itemId] = max(0.0, (float) $item->getRowTotal() - abs((float) $item->getDiscountAmount()));
            }
            $shipping = $this->calculator->allocate((float) $order->getShippingAmount(), $costs);
            $declared = $declarations[$orderId]['declared_sale_amount'] ?? null;
            $declaredShares = $declared === null ? [] : $this->calculator->allocate($declared, $costs);
            foreach ($items as $itemId => $item) {
                $modelId = (int) $item->getData('product_id');
                $qty = (float) $item->getQtyOrdered() - (float) $item->getQtyCanceled() - (float) $item->getQtyRefunded();
                if ($qty <= 0) {
                    continue;
                }
                [$sale, $source] = match (true) {
                    $declared !== null => [$declaredShares[$itemId], self::SOURCE_ORDER],
                    isset($prices[$modelId]) => [round($prices[$modelId] * $qty, 2), self::SOURCE_PRICE_LIST],
                    $publicFallback => [$this->publicPrice($modelId, (float) $item->getTaxPercent(), $qty), self::SOURCE_PUBLIC],
                    default => [null, null],
                };
                $lines[] = [
                    'order_id' => $orderId,
                    'order_increment_id' => (string) $order->getIncrementId(),
                    'time' => (int) strtotime((string) $order->getCreatedAt()),
                    'product_id' => $modelId,
                    'name' => $this->getName($modelId, (string) $item->getName()),
                    'qty' => $qty,
                    'cost' => round($costs[$itemId] + ($shipping[$itemId] ?? 0.0), 2),
                    'sale' => $sale,
                    'source' => $source,
                ];
            }
        }

        return $this->lines[$partnerId] = $lines;
    }

    /**
     * @return array<int, float> prix de vente HT de la boutique, par modèle
     */
    public function getPrices(int $partnerId): array
    {
        $connection = $this->resource->getConnection();
        $prices = [];
        foreach ($connection->fetchPairs(
            $connection->select()
                ->from($this->resource->getTableName('myca_partner_sale_price'), ['product_id', 'price_excl_tax'])
                ->where('partner_id = ?', $partnerId)
        ) as $productId => $price) {
            $prices[(int) $productId] = (float) $price;
        }

        return $prices;
    }

    /**
     * Modèles vendus en dropshipping par la boutique : ce sont eux dont elle peut saisir le prix de vente.
     *
     * @return array<int, string> id → nom
     */
    public function getModels(int $partnerId): array
    {
        $models = [];
        foreach ($this->getLines($partnerId) as $line) {
            $models[(int) $line['product_id']] = (string) $line['name'];
        }
        asort($models);

        return $models;
    }

    /**
     * Enregistre la grille de prix de la boutique. Un prix vide retire le modèle de la grille.
     *
     * @param array<int|string, string> $prices
     * @throws LocalizedException
     */
    public function savePrices(int $partnerId, array $prices): int
    {
        $models = $this->getModels($partnerId);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('myca_partner_sale_price');
        $saved = 0;
        foreach ($prices as $productId => $value) {
            $productId = (int) $productId;
            if (!isset($models[$productId])) {
                throw new LocalizedException(__('Ce modèle ne fait pas partie de vos ventes.'));
            }
            $value = str_replace([' ', ','], ['', '.'], trim((string) $value));
            if ($value === '') {
                $connection->delete($table, ['partner_id = ?' => $partnerId, 'product_id = ?' => $productId]);
                continue;
            }
            if (!is_numeric($value) || (float) $value <= 0 || (float) $value > 100000) {
                throw new LocalizedException(__('Prix de vente invalide pour « %1 ».', $models[$productId]));
            }
            $connection->insertOnDuplicate(
                $table,
                ['partner_id' => $partnerId, 'product_id' => $productId, 'price_excl_tax' => round((float) $value, 2)],
                ['price_excl_tax']
            );
            $saved++;
        }
        unset($this->lines[$partnerId]);

        return $saved;
    }

    private function publicPrice(int $modelId, float $taxPercent, float $qty): ?float
    {
        $product = $this->loadProduct($modelId);
        if ($product === null) {
            return null;
        }
        $inclTax = (float) ($product->getData('msrp') ?: $product->getFinalPrice());

        return $inclTax > 0 ? round($inclTax / (1 + $taxPercent / 100) * $qty, 2) : null;
    }

    private function getName(int $modelId, string $fallback): string
    {
        if (!isset($this->names[$modelId])) {
            $product = $this->loadProduct($modelId);
            $this->names[$modelId] = $product ? (string) $product->getName() : $fallback;
        }

        return $this->names[$modelId];
    }

    private function loadProduct(int $productId): ?Product
    {
        try {
            $product = $this->productRepository->getById($productId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return $product instanceof Product ? $product : null;
    }
}
