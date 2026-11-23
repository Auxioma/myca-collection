<?php

declare(strict_types=1);

namespace Myca\Loyalty\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\SalesRule\Model\ResourceModel\Rule as RuleResource;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;
use Myca\Rma\Model\OrderEligibility;
use Myca\Rma\Model\StatusFlow;

/**
 * Règles du programme fidélité (cahier des charges « Fidélité : acquisition, retrait et retour ») :
 * - gain en attente à la commande, par ligne, sur le montant payé après remises et hors livraison ;
 * - validation une fois la commande payée, livrée et son délai de retour écoulé, sans retour en cours ;
 * - retrait au prorata de chaque remboursement, d'abord sur les points en attente ;
 * - points d'un bon restitués seulement en cas de remboursement total de la commande où il a servi.
 * Chaque mouvement porte une clé d'unicité : un événement rejoué ne compte jamais deux fois.
 */
class LoyaltyService
{
    private const SYSTEM = 'Système';

    public function __construct(
        private readonly Ledger $ledger,
        private readonly PointsCalculator $calculator,
        private readonly Config $config,
        private readonly OrderEligibility $eligibility,
        private readonly StatusFlow $statusFlow,
        private readonly PartnerResource $partnerResource,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly GroupCollectionFactory $groupCollectionFactory,
        private readonly ResourceConnection $resource
    ) {
    }

    public function earnForOrder(Order $order): void
    {
        $customerId = (int) $order->getCustomerId();
        $websiteId = (int) $order->getStore()->getWebsiteId();
        if ($customerId <= 0 || !$this->config->isEnabled($websiteId)) {
            return;
        }
        if (!$this->config->isB2bEnabled($websiteId) && $this->partnerResource->getPartnerIdByCustomerId($customerId) !== null) {
            return;
        }
        $rate = $this->config->getPointsPerEuro($websiteId);
        foreach ($order->getAllVisibleItems() as $item) {
            $points = $this->calculator->pointsForLine((float) $item->getRowTotalInclTax(), (float) $item->getDiscountAmount(), $rate);
            if ($points <= 0) {
                continue;
            }
            $this->ledger->record([
                'key' => 'earn:item:' . $item->getItemId(),
                'customer_id' => $customerId,
                'event' => Ledger::EARN,
                'pending_delta' => $points,
                'reason' => (string) __('Achat n° %1 – %2', $order->getIncrementId(), $item->getName()),
                'author' => self::SYSTEM,
                'order_id' => (int) $order->getId(),
                'order_increment_id' => (string) $order->getIncrementId(),
                'order_item_id' => (int) $item->getItemId(),
            ]);
        }
    }

    /**
     * Le bon d'achat fidélité saisi sur la commande devient « utilisé » (il ne peut servir qu'une fois).
     */
    public function markVoucherUsed(Order $order): void
    {
        $code = (string) $order->getCouponCode();
        if ($code === '') {
            return;
        }
        $connection = $this->resource->getConnection();
        $connection->update(
            $this->resource->getTableName('myca_loyalty_voucher'),
            ['status' => 'used', 'order_id' => (int) $order->getId()],
            ['code = ?' => $code, 'status = ?' => 'active']
        );
    }

    /**
     * Commande annulée : les points encore en attente de chaque ligne sont annulés.
     */
    public function cancelOrder(Order $order): void
    {
        $customerId = (int) $order->getCustomerId();
        if ($customerId <= 0) {
            return;
        }
        foreach ($order->getAllVisibleItems() as $item) {
            $state = $this->ledger->getLineState((int) $item->getItemId());
            if ($state['pending_left'] <= 0) {
                continue;
            }
            $this->ledger->record([
                'key' => 'cancel:item:' . $item->getItemId(),
                'customer_id' => $customerId,
                'event' => Ledger::CANCEL,
                'pending_delta' => -$state['pending_left'],
                'reason' => (string) __('Commande n° %1 annulée', $order->getIncrementId()),
                'author' => self::SYSTEM,
                'order_id' => (int) $order->getId(),
                'order_increment_id' => (string) $order->getIncrementId(),
                'order_item_id' => (int) $item->getItemId(),
            ]);
        }
    }

    /**
     * Remboursement (avoir) : retrait des points des seules lignes remboursées, au prorata des quantités.
     */
    public function applyCreditmemo(Creditmemo $creditmemo): void
    {
        $order = $creditmemo->getOrder();
        $customerId = (int) $order->getCustomerId();
        if ($customerId <= 0 || !$creditmemo->getId()) {
            return;
        }
        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            $orderItem = $creditmemoItem->getOrderItem();
            if ($orderItem->getParentItemId() || (float) $creditmemoItem->getQty() <= 0) {
                continue;
            }
            $state = $this->ledger->getLineState((int) $orderItem->getItemId());
            $toRemove = $this->calculator->pointsToRemove(
                $state['earned'],
                (float) $orderItem->getQtyOrdered(),
                (float) $orderItem->getQtyRefunded(),
                $state['removed']
            );
            if ($toRemove <= 0) {
                continue;
            }
            $split = $this->calculator->splitRemoval($toRemove, $state['pending_left']);
            $this->ledger->record([
                'key' => 'refund:cm:' . $creditmemo->getId() . ':item:' . $orderItem->getItemId(),
                'customer_id' => $customerId,
                'event' => Ledger::REFUND,
                'pending_delta' => -$split['pending'],
                'available_delta' => -$split['available'],
                'reason' => (string) __('Retour – commande n° %1 – %2', $order->getIncrementId(), $orderItem->getName()),
                'author' => self::SYSTEM,
                'order_id' => (int) $order->getId(),
                'order_increment_id' => (string) $order->getIncrementId(),
                'order_item_id' => (int) $orderItem->getItemId(),
                'creditmemo_id' => (int) $creditmemo->getId(),
            ]);
        }
        if ($this->isFullyRefunded($order)) {
            $this->restoreVoucherPoints($order);
        }
    }

    /**
     * Valide les points en attente des commandes dont le délai de retour est écoulé (tâche planifiée).
     *
     * @return int nombre de lignes validées
     */
    public function validateDue(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $validated = 0;
        foreach ($this->ledger->getOrdersWithPendingPoints() as $orderId) {
            $order = $this->orderRepository->get($orderId);
            if (!$order instanceof Order || !$this->isValidable($order, $now)) {
                continue;
            }
            foreach ($order->getAllVisibleItems() as $item) {
                $state = $this->ledger->getLineState((int) $item->getItemId());
                if ($state['pending_left'] <= 0) {
                    continue;
                }
                $validated += (int) $this->ledger->record([
                    'key' => 'validate:item:' . $item->getItemId(),
                    'customer_id' => (int) $order->getCustomerId(),
                    'event' => Ledger::VALIDATE,
                    'pending_delta' => -$state['pending_left'],
                    'available_delta' => $state['pending_left'],
                    'reason' => (string) __('Points validés – commande n° %1', $order->getIncrementId()),
                    'author' => self::SYSTEM,
                    'order_id' => (int) $order->getId(),
                    'order_increment_id' => (string) $order->getIncrementId(),
                    'order_item_id' => (int) $item->getItemId(),
                ]);
            }
        }

        return $validated;
    }

    /**
     * Transforme des points en bon d'achat à usage unique, réservé au client.
     *
     * @return array{code: string, amount: float}
     * @throws LocalizedException
     */
    public function redeem(int $customerId, int $points): array
    {
        $customer = $this->customerRepository->getById($customerId);
        $websiteId = (int) $customer->getWebsiteId();
        $rewards = $this->config->getRewards($websiteId);
        if (!$this->config->isEnabled($websiteId) || !isset($rewards[$points])) {
            throw new LocalizedException(__('Cette récompense n’est pas disponible.'));
        }
        $amount = $rewards[$points];
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $account = $this->ledger->lockAccount($customerId);
            if ($account['available'] < $points) {
                throw new LocalizedException(__('Vous n’avez pas assez de points utilisables pour ce bon.'));
            }
            $code = 'MYCA-' . strtoupper(bin2hex(random_bytes(5)));
            $rule = $this->createVoucherRule($code, $amount, $websiteId, $customerId);
            $connection->insert($this->resource->getTableName('myca_loyalty_voucher'), [
                'customer_id' => $customerId,
                'rule_id' => (int) $rule->getId(),
                'code' => $code,
                'amount' => $amount,
                'points' => $points,
            ]);
            $voucherId = (int) $connection->fetchOne(
                $connection->select()->from($this->resource->getTableName('myca_loyalty_voucher'), ['voucher_id'])->where('code = ?', $code)
            );
            $this->ledger->record([
                'key' => 'redeem:voucher:' . $voucherId,
                'customer_id' => $customerId,
                'event' => Ledger::REDEEM,
                'available_delta' => -$points,
                'reason' => (string) __('Bon d’achat de %1 € (%2)', $this->formatAmount($amount), $code),
                'author' => (string) __('Client'),
                'voucher_id' => $voucherId,
            ]);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return ['code' => $code, 'amount' => $amount];
    }

    /**
     * Expire les points utilisables des clients sans gain depuis la durée configurée (tâche planifiée).
     */
    public function expireInactive(?\DateTimeImmutable $now = null): int
    {
        $months = $this->config->getExpiryMonths();
        if ($months <= 0) {
            return 0;
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $since = $now->modify(sprintf('-%d months', $months))->format('Y-m-d H:i:s');
        $expired = 0;
        foreach ($this->ledger->getInactiveCustomers($since) as $customerId => $lastTransactionId) {
            $available = $this->ledger->getAccount($customerId)['available'];
            if ($available <= 0) {
                continue;
            }
            $expired += (int) $this->ledger->record([
                'key' => 'expire:' . $customerId . ':' . $lastTransactionId,
                'customer_id' => $customerId,
                'event' => Ledger::EXPIRE,
                'available_delta' => -$available,
                'reason' => (string) __('Points expirés après %1 mois sans achat', $months),
                'author' => self::SYSTEM,
            ]);
        }

        return $expired;
    }

    /**
     * Ajustement manuel par le service client. Le jeton du formulaire évite un double enregistrement.
     *
     * @throws LocalizedException
     */
    public function adjust(int $customerId, int $points, string $reason, string $adminName, string $token): bool
    {
        $reason = trim($reason);
        if ($points === 0 || $reason === '') {
            throw new LocalizedException(__('Indiquez un nombre de points non nul et un motif.'));
        }
        $this->customerRepository->getById($customerId);

        return $this->ledger->record([
            'key' => 'adjust:' . $token,
            'customer_id' => $customerId,
            'event' => Ledger::ADJUST,
            'available_delta' => $points,
            'reason' => $reason,
            'author' => $adminName,
        ]);
    }

    private function isValidable(Order $order, \DateTimeImmutable $now): bool
    {
        if ($order->getState() === Order::STATE_CANCELED || !$order->hasInvoices()) {
            return false;
        }
        $deadline = $this->eligibility->getDeadline($order, \Myca\Rma\Model\ReturnPolicy::TYPE_RETURN);
        if ($deadline === null || $deadline > $now) {
            return false;
        }

        return !$this->hasOpenReturn((int) $order->getId());
    }

    private function hasOpenReturn(int $orderId): bool
    {
        $open = array_values(array_filter(
            array_keys($this->statusFlow->getLabels()),
            fn (string $status) => $this->statusFlow->isOpen($status)
        ));
        $connection = $this->resource->getConnection();

        return (bool) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('myca_rma'), ['rma_id'])
                ->where('order_id = ?', $orderId)
                ->where('status IN (?)', $open)
        );
    }

    private function isFullyRefunded(Order $order): bool
    {
        foreach ($order->getAllVisibleItems() as $item) {
            if ((float) $item->getQtyRefunded() < (float) $item->getQtyInvoiced()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rétractation totale : les points du bon utilisé sur la commande sont restitués, une seule fois.
     */
    private function restoreVoucherPoints(Order $order): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('myca_loyalty_voucher');
        $voucher = $connection->fetchRow(
            $connection->select()->from($table)->where('order_id = ?', (int) $order->getId())->where('status = ?', 'used')
        );
        if (!$voucher) {
            return;
        }
        $restored = $this->ledger->record([
            'key' => 'restore:voucher:' . $voucher['voucher_id'],
            'customer_id' => (int) $voucher['customer_id'],
            'event' => Ledger::RESTORE,
            'available_delta' => (int) $voucher['points'],
            'reason' => (string) __('Bon %1 restitué – commande n° %2 remboursée', $voucher['code'], $order->getIncrementId()),
            'author' => self::SYSTEM,
            'order_id' => (int) $order->getId(),
            'order_increment_id' => (string) $order->getIncrementId(),
            'voucher_id' => (int) $voucher['voucher_id'],
        ]);
        if ($restored) {
            $connection->update($table, ['status' => 'restored'], ['voucher_id = ?' => (int) $voucher['voucher_id']]);
        }
    }

    private function createVoucherRule(string $code, float $amount, int $websiteId, int $customerId): Rule
    {
        $rule = $this->ruleFactory->create();
        $rule->setData([
            'name' => sprintf('Bon fidélité MYCA %s € – client %d', $this->formatAmount($amount), $customerId),
            'description' => 'Créé par le programme fidélité. Usage unique, réservé au client.',
            'is_active' => 1,
            'website_ids' => [$websiteId],
            'customer_group_ids' => $this->groupCollectionFactory->create()->getAllIds(),
            'coupon_type' => Rule::COUPON_TYPE_SPECIFIC,
            'coupon_code' => $code,
            'uses_per_coupon' => 1,
            'uses_per_customer' => 1,
            'simple_action' => Rule::CART_FIXED_ACTION,
            'discount_amount' => $amount,
            'apply_to_shipping' => 0,
            'stop_rules_processing' => 0,
        ]);
        $this->ruleResource->save($rule);

        return $rule;
    }

    private function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',');
    }
}
