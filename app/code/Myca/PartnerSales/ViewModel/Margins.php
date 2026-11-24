<?php

declare(strict_types=1);

namespace Myca\PartnerSales\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;
use Myca\PartnerSales\Model\MarginCalculator;
use Myca\PartnerSales\Model\SalesData;

/**
 * M21 — Mes marges & ventes, pour la boutique active uniquement.
 */
class Margins implements ArgumentInterface
{
    public const PERIODS = [30 => '30 derniers jours', 90 => '3 derniers mois', 365 => '12 derniers mois'];

    public function __construct(
        private readonly SalesData $salesData,
        private readonly MarginCalculator $calculator,
        private readonly CurrentPartner $currentPartner,
        private readonly PartnerResource $partnerResource,
        private readonly CustomerSession $customerSession,
        private readonly RequestInterface $request,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getPeriod(): int
    {
        $period = (int) $this->request->getParam('periode', 30);

        return isset(self::PERIODS[$period]) ? $period : 30;
    }

    public function getView(): string
    {
        $view = (string) $this->request->getParam('vue', 'ensemble');

        return in_array($view, ['ensemble', 'ventes', 'produits', 'prix'], true) ? $view : 'ensemble';
    }

    /**
     * @return array{current: array<string, mixed>, previous: array<string, mixed>}
     */
    public function getSummaries(): array
    {
        [$from, $to, $previousFrom] = $this->getRange();
        $lines = $this->getLines();

        return [
            'current' => $this->calculator->summarize($lines, $from, $to),
            'previous' => $this->calculator->summarize($lines, $previousFrom, $from),
        ];
    }

    public function variation(?float $current, ?float $previous): ?int
    {
        return $this->calculator->variation($current, $previous);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(): array
    {
        [$from, $to] = $this->getRange();

        return $this->calculator->byProduct($this->getLines(), $from, $to);
    }

    /**
     * @return array<string, float>
     */
    public function getMonthly(): array
    {
        return $this->calculator->monthly($this->getLines(), 7, time());
    }

    /**
     * Lignes de la période, les plus récentes d'abord.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPeriodLines(): array
    {
        [$from, $to] = $this->getRange();
        $lines = array_values(array_filter($this->getLines(), static fn (array $line) => $line['time'] >= $from && $line['time'] < $to));
        usort($lines, static fn (array $a, array $b) => $b['time'] <=> $a['time']);

        return $lines;
    }

    /**
     * Commandes de la période, avec leur achat MYCA, leur vente et l'origine du prix.
     *
     * @return array<int, array{order_id: int, increment_id: string, time: int, cost: float, sale: float|null, source: string|null, qty: float}>
     */
    public function getPeriodOrders(): array
    {
        $orders = [];
        foreach ($this->getPeriodLines() as $line) {
            $id = (int) $line['order_id'];
            $orders[$id] ??= ['order_id' => $id, 'increment_id' => (string) $line['order_increment_id'], 'time' => (int) $line['time'],
                'cost' => 0.0, 'sale' => 0.0, 'source' => $line['source'], 'qty' => 0.0, 'unknown' => false];
            $orders[$id]['cost'] += $line['cost'];
            $orders[$id]['qty'] += $line['qty'];
            if ($line['sale'] === null) {
                $orders[$id]['unknown'] = true;
            } else {
                $orders[$id]['sale'] += $line['sale'];
            }
        }
        foreach ($orders as $id => $order) {
            $orders[$id]['sale'] = $order['unknown'] ? null : round((float) $order['sale'], 2);
            unset($orders[$id]['unknown']);
        }

        return array_values($orders);
    }

    /**
     * @return array<int, string>
     */
    public function getModels(): array
    {
        $partnerId = $this->currentPartner->getActiveId();

        return $partnerId === null ? [] : $this->salesData->getModels($partnerId);
    }

    /**
     * @return array<int, float>
     */
    public function getPrices(): array
    {
        $partnerId = $this->currentPartner->getActiveId();

        return $partnerId === null ? [] : $this->salesData->getPrices($partnerId);
    }

    public function isOwner(): bool
    {
        $partnerId = $this->currentPartner->getActiveId();

        return $partnerId !== null
            && $this->partnerResource->getUserRole($partnerId, (int) $this->customerSession->getCustomerId()) === 'owner';
    }

    public function isPublicFallback(): bool
    {
        return $this->scopeConfig->isSetFlag('myca_partner_sales/margin/public_price_fallback');
    }

    public function getSourceLabel(?string $source): string
    {
        return match ($source) {
            SalesData::SOURCE_ORDER => (string) __('Déclaré sur la commande'),
            SalesData::SOURCE_PRICE_LIST => (string) __('Votre grille de prix'),
            SalesData::SOURCE_PUBLIC => (string) __('Estimé (prix public)'),
            default => (string) __('Non déclaré'),
        };
    }

    public function formatPrice(?float $amount): string
    {
        return $amount === null ? '—' : (string) $this->priceCurrency->format($amount, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getLines(): array
    {
        $partnerId = $this->currentPartner->getActiveId();

        return $partnerId === null ? [] : $this->salesData->getLines($partnerId);
    }

    /**
     * @return array{0: int, 1: int, 2: int} début, fin, début de la période précédente
     */
    private function getRange(): array
    {
        $days = $this->getPeriod();
        $to = time() + 1;
        $from = $to - $days * 86400;

        return [$from, $to, $from - $days * 86400];
    }
}
