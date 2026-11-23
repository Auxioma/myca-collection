<?php

declare(strict_types=1);

namespace Myca\Loyalty\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Myca\Loyalty\Model\Config;
use Myca\Loyalty\Model\Ledger;
use Myca\Loyalty\Model\PointsCalculator;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;

/**
 * M36 — programme fidélité du client connecté : soldes, paliers, bons, historique.
 */
class Program implements ArgumentInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly Config $config,
        private readonly Ledger $ledger,
        private readonly PointsCalculator $calculator,
        private readonly PartnerResource $partnerResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Programme actif et ouvert à ce client (les comptes boutique en sont exclus sauf points B2B activés).
     */
    public function isAvailable(): bool
    {
        $websiteId = (int) $this->storeManager->getWebsite()->getId();
        if (!$this->config->isEnabled($websiteId)) {
            return false;
        }
        $customerId = $this->getCustomerId();

        return $customerId > 0
            && ($this->config->isB2bEnabled($websiteId) || $this->partnerResource->getPartnerIdByCustomerId($customerId) === null);
    }

    /**
     * @return array{available: int, pending: int}
     */
    public function getAccount(): array
    {
        return $this->ledger->getAccount($this->getCustomerId());
    }

    /**
     * @return array{used: int, canceled: int, expired: int}
     */
    public function getTotals(): array
    {
        return $this->ledger->getTotals($this->getCustomerId());
    }

    /**
     * @return array<int, float>
     */
    public function getRewards(): array
    {
        return $this->config->getRewards((int) $this->storeManager->getWebsite()->getId());
    }

    /**
     * @return array{points: int, amount: float}|null
     */
    public function getNextReward(): ?array
    {
        return $this->calculator->nextReward($this->getRewards(), $this->getAccount()['available']);
    }

    public function getPointsPerEuro(): float
    {
        return $this->config->getPointsPerEuro((int) $this->storeManager->getWebsite()->getId());
    }

    public function getExpiryMonths(): int
    {
        return $this->config->getExpiryMonths((int) $this->storeManager->getWebsite()->getId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(?int $limit = null): array
    {
        return $this->ledger->getHistory($this->getCustomerId(), $limit);
    }

    /**
     * Bons d'achat encore utilisables.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveVouchers(): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('myca_loyalty_voucher'))
                ->where('customer_id = ?', $this->getCustomerId())
                ->where('status = ?', 'active')
                ->order('voucher_id DESC')
        );
    }

    /**
     * Variation affichée dans l'historique : points utilisables, ou en attente pour un gain non encore validé.
     *
     * @param array<string, mixed> $entry
     * @return array{points: int, pending: bool}
     */
    public function getEntryPoints(array $entry): array
    {
        $available = (int) $entry['available_delta'];
        $pending = (int) $entry['pending_delta'];
        if ((string) $entry['event'] === Ledger::VALIDATE) {
            return ['points' => $available, 'pending' => false];
        }

        return $available !== 0 ? ['points' => $available, 'pending' => false] : ['points' => $pending, 'pending' => true];
    }

    public function getEventLabel(string $event): string
    {
        return match ($event) {
            Ledger::EARN => (string) __('Points gagnés (en attente)'),
            Ledger::VALIDATE => (string) __('Points validés'),
            Ledger::CANCEL => (string) __('Annulation'),
            Ledger::REFUND => (string) __('Retour'),
            Ledger::REDEEM => (string) __('Bon d’achat'),
            Ledger::RESTORE => (string) __('Points restitués'),
            Ledger::EXPIRE => (string) __('Expiration'),
            default => (string) __('Ajustement'),
        };
    }

    public function formatPrice(float $amount): string
    {
        return (string) $this->priceCurrency->format($amount, false);
    }

    private function getCustomerId(): int
    {
        return (int) $this->customerSession->getCustomerId();
    }
}
