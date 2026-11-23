<?php

declare(strict_types=1);

namespace Myca\Rma\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;

/**
 * Seul point d'écriture des demandes : création, changements d'état, messages, contrôle et remboursement.
 * Les écrans (client, partenaire, administration) passent tous par ici ; les droits d'accès à la commande sont
 * vérifiés par l'appelant, les règles métier ici.
 */
class RmaManagement
{
    public const AUTHOR_CUSTOMER = 'customer';
    public const AUTHOR_PARTNER = 'partner';
    public const AUTHOR_ADMIN = 'admin';

    public function __construct(
        private readonly RmaFactory $rmaFactory,
        private readonly RmaResource $rmaResource,
        private readonly ReturnPolicy $policy,
        private readonly StatusFlow $statusFlow,
        private readonly Reasons $reasons,
        private readonly OrderEligibility $eligibility,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoManagementInterface $creditmemoManagement
    ) {
    }

    /**
     * @param array<int, float|int|string> $qtys quantités demandées par ligne visible de la commande
     * @param array<int, string> $exchangeWishes taille ou coloris souhaité, par ligne (échange)
     * @param string|null $origin origine déclarée par la boutique quand la commande n'en porte pas
     * @throws LocalizedException
     */
    public function create(
        Order $order,
        string $type,
        string $reason,
        array $qtys,
        array $exchangeWishes,
        ?string $comment,
        string $authorType,
        string $authorName,
        ?int $customerId,
        ?string $origin = null
    ): Rma {
        $orderOrigin = $this->eligibility->getOrigin($order);
        $origin = $orderOrigin ?? $origin;
        if (!in_array($origin, ReturnPolicy::ORIGINS, true)) {
            throw new LocalizedException(__('Indiquez comment cet article a été vendu : en dropshipping ou en boutique.'));
        }
        if ($orderOrigin === ReturnPolicy::ORIGIN_ONLINE && $authorType === self::AUTHOR_PARTNER
            || $orderOrigin !== ReturnPolicy::ORIGIN_ONLINE && $authorType === self::AUTHOR_CUSTOMER
        ) {
            throw new LocalizedException(__('Cette demande doit être déposée depuis l’espace qui a passé la commande.'));
        }
        if (!in_array($type, $this->policy->getAllowedTypes($origin), true)) {
            throw new LocalizedException(
                $origin === ReturnPolicy::ORIGIN_STORE
                    ? __('Un article vendu en boutique se retourne au comptoir de la boutique. Seul un dossier SAV (défaut) peut être déposé auprès de MYCA.')
                    : __('Type de demande inconnu.')
            );
        }
        if (!array_key_exists($reason, $this->reasons->getOptions($type))) {
            throw new LocalizedException(__('Choisissez un motif.'));
        }
        $comment = trim((string) $comment);
        if (mb_strlen($comment) > 1000) {
            throw new LocalizedException(__('Le commentaire ne doit pas dépasser 1 000 caractères.'));
        }
        if (($reason === 'other' || $type === ReturnPolicy::TYPE_WARRANTY) && $comment === '') {
            throw new LocalizedException(__('Décrivez le problème en quelques mots.'));
        }

        $connection = $this->rmaResource->connection();
        $connection->beginTransaction();
        try {
            $this->rmaResource->lockOrder((int) $order->getId());
            $blocking = $this->eligibility->getBlockingReasons($order, $type);
            if ($blocking) {
                throw new LocalizedException(__(reset($blocking)));
            }
            $items = $this->buildItems($order, $qtys, $exchangeWishes, $type);

            $deadline = $this->eligibility->getDeadline($order, $type);
            $rma = $this->rmaFactory->create();
            $rma->setData([
                'order_id' => (int) $order->getId(),
                'order_increment_id' => (string) $order->getIncrementId(),
                'origin' => $origin,
                'type' => $type,
                'status' => StatusFlow::CREATED,
                'refund_party' => $this->policy->getRefundParty($origin),
                'customer_id' => $customerId,
                'customer_email' => (string) $order->getCustomerEmail(),
                'partner_id' => $this->eligibility->getPartnerId($order),
                'reason' => $reason,
                'comment' => $comment === '' ? null : $comment,
                'policy_days' => $this->eligibility->getPolicyDays($order, $type),
                'deadline_at' => $deadline?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ]);
            $this->rmaResource->save($rma);
            $rma->setData('increment_id', sprintf('SAV-%s-%04d', date('Y'), (int) $rma->getId()));
            $this->rmaResource->save($rma);
            $this->rmaResource->insertItems((int) $rma->getId(), $items);
            $this->rmaResource->addHistory((int) $rma->getId(), StatusFlow::CREATED, $authorType, $authorName, $comment ?: null);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $rma;
    }

    /**
     * @throws LocalizedException
     */
    public function changeStatus(Rma $rma, string $status, string $actor, string $authorType, string $authorName, ?string $message = null): void
    {
        $connection = $this->rmaResource->connection();
        $connection->beginTransaction();
        try {
            $current = (string) ($this->rmaResource->lockRow((int) $rma->getId())['status'] ?? '');
            if (!$this->statusFlow->canTransition($current, $status, $actor)) {
                throw new LocalizedException(__('Cette action n’est plus possible sur la demande %1.', $rma->getIncrementId()));
            }
            $rma->setData('status', $status);
            $this->rmaResource->save($rma);
            $this->rmaResource->addHistory((int) $rma->getId(), $status, $authorType, $authorName, $this->cleanMessage($message));
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @throws LocalizedException
     */
    public function addMessage(Rma $rma, string $authorType, string $authorName, string $message, bool $visible = true): void
    {
        $message = $this->cleanMessage($message);
        if ($message === null) {
            throw new LocalizedException(__('Le message est vide.'));
        }
        $this->rmaResource->addHistory((int) $rma->getId(), $rma->getStatus(), $authorType, $authorName, $message, $visible);
    }

    /**
     * Dropshipping : la boutique déclare avoir remboursé son client (MYCA ne rembourse jamais ce consommateur).
     *
     * @throws LocalizedException
     */
    public function declarePartnerRefund(Rma $rma, string $authorName): void
    {
        if ($rma->getOrigin() !== ReturnPolicy::ORIGIN_DROPSHIP || (int) $rma->getData('partner_refunded_customer') === 1) {
            throw new LocalizedException(__('Cette déclaration ne s’applique pas à la demande %1.', $rma->getIncrementId()));
        }
        $rma->setData('partner_refunded_customer', 1);
        $this->rmaResource->save($rma);
        $this->rmaResource->addHistory(
            (int) $rma->getId(),
            $rma->getStatus(),
            self::AUTHOR_PARTNER,
            $authorName,
            (string) __('La boutique déclare avoir remboursé son client.')
        );
    }

    /**
     * Contrôle à réception : l'état de chaque article décide de sa remise en stock. Rien n'est remis en vente avant.
     *
     * @param array<int, string> $conditions état par rma_item_id
     * @throws LocalizedException
     */
    public function inspect(Rma $rma, array $conditions, string $authorName, ?string $message = null): void
    {
        $allowed = array_keys($this->reasons->getConditionLabels());
        $items = $this->rmaResource->getItems((int) $rma->getId());
        foreach ($items as $item) {
            $condition = $conditions[(int) $item['rma_item_id']] ?? '';
            if (!in_array($condition, $allowed, true)) {
                throw new LocalizedException(__('Indiquez l’état de chaque article contrôlé.'));
            }
        }
        $connection = $this->rmaResource->connection();
        $connection->beginTransaction();
        try {
            foreach ($items as $item) {
                $this->rmaResource->setItemCondition((int) $rma->getId(), (int) $item['rma_item_id'], $conditions[(int) $item['rma_item_id']]);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $this->changeStatus($rma, StatusFlow::INSPECTED, StatusFlow::ACTOR_ADMIN, self::AUTHOR_ADMIN, $authorName, $message);
    }

    /**
     * Émet le remboursement (achat en ligne) ou l'avoir partenaire (dropshipping, garantie boutique) sur la commande
     * MYCA, au payeur de cette commande. Rejouer l'action ne crée jamais un second avoir.
     *
     * @throws LocalizedException
     */
    public function refund(Rma $rma, string $authorName): int
    {
        if (!$this->policy->issuesMycaCredit($rma->getOrigin(), $rma->getType())) {
            throw new LocalizedException(
                $rma->getOrigin() === ReturnPolicy::ORIGIN_STORE
                    ? __('Un retour en boutique physique n’ouvre aucun remboursement MYCA.')
                    : __('Un échange ne donne pas lieu à un remboursement.')
            );
        }
        $connection = $this->rmaResource->connection();
        $connection->beginTransaction();
        try {
            $row = $this->rmaResource->lockRow((int) $rma->getId());
            if (!empty($row['creditmemo_id'])) {
                $connection->commit();

                return (int) $row['creditmemo_id'];
            }
            if (!$this->statusFlow->canRefund((string) ($row['status'] ?? ''))) {
                throw new LocalizedException(__('Les articles doivent être réceptionnés et contrôlés avant le remboursement.'));
            }
            $order = $this->orderRepository->get($rma->getOrderId());
            if (!$order instanceof Order || !$order->canCreditmemo()) {
                throw new LocalizedException(__('Cette commande ne peut plus être remboursée (non facturée ou déjà remboursée).'));
            }

            $items = $this->rmaResource->getItems((int) $rma->getId());
            $qtys = [];
            $restock = [];
            foreach ($items as $item) {
                $itemId = (int) $item['order_item_id'];
                $qtys[$itemId] = ($qtys[$itemId] ?? 0.0) + (float) $item['qty'];
                // Le stock d'une boutique physique ne revient jamais dans le stock central.
                if ($item['item_condition'] === 'resellable' && $rma->getOrigin() !== ReturnPolicy::ORIGIN_STORE) {
                    $restock[$itemId] = true;
                }
            }
            $refundable = [];
            foreach ($order->getAllVisibleItems() as $orderItem) {
                $refundable[(int) $orderItem->getItemId()] = (float) $orderItem->getQtyInvoiced() - (float) $orderItem->getQtyRefunded();
            }
            $shipping = $this->policy->refundsShipping($rma->getOrigin(), $rma->getType(), $qtys, $refundable)
                ? (float) $order->getBaseShippingAmount() - (float) $order->getBaseShippingRefunded()
                : 0.0;

            $creditmemo = $this->creditmemoFactory->createByOrder($order, [
                'qtys' => $qtys,
                'shipping_amount' => $shipping,
                'adjustment_positive' => 0,
                'adjustment_negative' => 0,
            ]);
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                $orderItem = $creditmemoItem->getOrderItem();
                $parentId = (int) ($orderItem->getParentItemId() ?: $orderItem->getItemId());
                $creditmemoItem->setData('back_to_stock', isset($restock[$parentId]));
            }
            $creditmemo->addComment((string) __('Demande %1', $rma->getIncrementId()), false, false);
            $creditmemo = $this->creditmemoManagement->refund($creditmemo, true);

            $rma->setData('creditmemo_id', (int) $creditmemo->getEntityId());
            $rma->setData('refunded_amount', (float) $creditmemo->getGrandTotal());
            $rma->setData('status', StatusFlow::REFUNDED);
            $this->rmaResource->save($rma);
            $this->rmaResource->addHistory(
                (int) $rma->getId(),
                StatusFlow::REFUNDED,
                self::AUTHOR_ADMIN,
                $authorName,
                $rma->getOrigin() === ReturnPolicy::ORIGIN_ONLINE
                    ? (string) __('Remboursement de %1 émis au payeur de la commande.', $order->formatPriceTxt((float) $creditmemo->getGrandTotal()))
                    : (string) __('Avoir MYCA de %1 émis à la boutique.', $order->formatPriceTxt((float) $creditmemo->getGrandTotal()))
            );
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return (int) $creditmemo->getEntityId();
    }

    /**
     * @param array<int, float|int|string> $qtys
     * @param array<int, string> $exchangeWishes
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    private function buildItems(Order $order, array $qtys, array $exchangeWishes, string $type): array
    {
        $returnable = $this->eligibility->getReturnableQtys($order);
        $items = [];
        foreach ($order->getAllVisibleItems() as $orderItem) {
            $itemId = (int) $orderItem->getItemId();
            $qty = (float) ($qtys[$itemId] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            if ($qty != floor($qty) || $qty > ($returnable[$itemId] ?? 0.0)) {
                throw new LocalizedException(__('Quantité non disponible pour « %1 ».', $orderItem->getName()));
            }
            $wish = trim((string) ($exchangeWishes[$itemId] ?? ''));
            if ($type === ReturnPolicy::TYPE_EXCHANGE && $wish === '') {
                throw new LocalizedException(__('Indiquez la taille ou le coloris souhaité pour « %1 ».', $orderItem->getName()));
            }
            $items[] = [
                'order_item_id' => $itemId,
                'sku' => (string) $orderItem->getSku(),
                'name' => (string) $orderItem->getName(),
                'qty' => $qty,
                'exchange_wish' => $type === ReturnPolicy::TYPE_EXCHANGE ? mb_substr($wish, 0, 255) : null,
            ];
        }
        foreach (array_keys($qtys) as $itemId) {
            if (!array_key_exists((int) $itemId, $returnable)) {
                throw new LocalizedException(__('Un article ne fait pas partie de cette commande.'));
            }
        }
        if (!$items) {
            throw new LocalizedException(__('Choisissez au moins un article à retourner.'));
        }

        return $items;
    }

    private function cleanMessage(?string $message): ?string
    {
        $message = trim((string) $message);

        return $message === '' ? null : mb_substr($message, 0, 2000);
    }
}
