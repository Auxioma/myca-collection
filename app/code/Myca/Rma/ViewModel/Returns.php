<?php

declare(strict_types=1);

namespace Myca\Rma\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Myca\Partner\Model\CurrentPartner;
use Myca\Rma\Controller\Voir\Index as CustomerRmaPage;
use Myca\Rma\Model\OrderEligibility;
use Myca\Rma\Model\Reasons;
use Myca\Rma\Model\RequesterAccess;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;
use Myca\Rma\Model\ResourceModel\Rma\CollectionFactory;
use Myca\Rma\Model\ReturnPolicy;
use Myca\Rma\Model\Rma;
use Myca\Rma\Model\StatusFlow;

/**
 * Demandes de retour pour les gabarits du client (M29, retours) et de la boutique (M23).
 * Toutes les lectures sont limitées au client connecté ou à la boutique active.
 */
class Returns implements ArgumentInterface
{
    /** @var array<int, Order|null> */
    private array $orders = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly RmaResource $rmaResource,
        private readonly StatusFlow $statusFlow,
        private readonly Reasons $reasons,
        private readonly ReturnPolicy $policy,
        private readonly OrderEligibility $eligibility,
        private readonly RequesterAccess $access,
        private readonly CustomerSession $customerSession,
        private readonly CurrentPartner $currentPartner,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RequestInterface $request,
        private readonly Registry $registry
    ) {
    }

    public function isCustomerLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * Demandes du client connecté : uniquement celles de ses achats MYCA en ligne.
     *
     * @return Rma[]
     */
    public function getCustomerRmas(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->getSelect()->join(
            ['sales_order' => $collection->getTable('sales_order')],
            'sales_order.entity_id = main_table.order_id',
            []
        )->where('sales_order.customer_id = ?', $customerId);
        $collection->addFieldToFilter('main_table.partner_id', ['null' => true])->setOrder('main_table.rma_id', 'DESC');
        /** @var Rma[] $items */
        $items = $collection->getItems();

        return array_values($items);
    }

    /**
     * @return Rma[]
     */
    public function getPartnerRmas(): array
    {
        $partnerId = $this->currentPartner->getActiveId();
        if ($partnerId === null) {
            return [];
        }
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('partner_id', ['eq' => $partnerId])
            ->setOrder('rma_id', 'DESC');
        /** @var Rma[] $items */
        $items = $collection->getItems();

        return array_values($items);
    }

    /**
     * Demande ouverte par le contrôleur client (Controller\Voir\Index), après contrôle d'accès.
     */
    public function getCurrentCustomerRma(): ?Rma
    {
        $rma = $this->registry->registry(CustomerRmaPage::REGISTRY_KEY);

        return $rma instanceof Rma ? $rma : null;
    }

    /**
     * Commande de la page « Demander un retour », déjà contrôlée par le contrôleur.
     */
    public function getRequestOrder(bool $partner): ?Order
    {
        $orderId = (int) $this->request->getParam('commande');

        return $partner ? $this->access->getPartnerOrder($orderId) : $this->access->getCustomerOrder($this->request, $orderId);
    }

    public function getOrder(Rma $rma): ?Order
    {
        $orderId = $rma->getOrderId();
        if (!array_key_exists($orderId, $this->orders)) {
            try {
                $order = $this->orderRepository->get($orderId);
                $this->orders[$orderId] = $order instanceof Order ? $order : null;
            } catch (NoSuchEntityException) {
                $this->orders[$orderId] = null;
            }
        }

        return $this->orders[$orderId];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(Rma $rma): array
    {
        return $this->rmaResource->getItems((int) $rma->getId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(Rma $rma): array
    {
        return $this->rmaResource->getHistory((int) $rma->getId(), true);
    }

    /**
     * @return array{label: string, tone: string}
     */
    public function getStatus(Rma $rma): array
    {
        return $this->statusFlow->getLabel($rma->getStatus());
    }

    public function getStatusLabel(string $status): string
    {
        return $this->statusFlow->getLabel($status)['label'];
    }

    public function isOpen(Rma $rma): bool
    {
        return $this->statusFlow->isOpen($rma->getStatus());
    }

    /**
     * @return string[] actions du demandeur : annuler, expedie
     */
    public function getRequesterActions(Rma $rma): array
    {
        $actions = [];
        foreach ($this->statusFlow->getAllowedTransitions($rma->getStatus(), StatusFlow::ACTOR_REQUESTER) as $status) {
            $actions[] = $status === StatusFlow::CANCELED ? 'annuler' : 'expedie';
        }

        return $actions;
    }

    public function canDeclarePartnerRefund(Rma $rma): bool
    {
        return $rma->getOrigin() === ReturnPolicy::ORIGIN_DROPSHIP
            && (int) $rma->getData('partner_refunded_customer') === 0
            && in_array($rma->getStatus(), [StatusFlow::RECEIVED, StatusFlow::INSPECTED, StatusFlow::REFUNDED, StatusFlow::CLOSED], true);
    }

    public function getTypeLabel(Rma|string $type): string
    {
        $type = $type instanceof Rma ? $type->getType() : $type;

        return $this->reasons->getTypeLabels()[$type] ?? $type;
    }

    public function getTypeTone(string $type): string
    {
        return match ($type) {
            ReturnPolicy::TYPE_RETURN => 'danger',
            ReturnPolicy::TYPE_EXCHANGE => 'info',
            default => 'warning',
        };
    }

    /**
     * @return array<string, string>
     */
    public function getTypeLabels(): array
    {
        return $this->reasons->getTypeLabels();
    }

    public function getReasonLabel(Rma $rma): string
    {
        return $this->reasons->getLabel((string) $rma->getData('reason'));
    }

    /**
     * @return array<string, array<string, string>> motifs par type de demande
     */
    public function getReasonsByType(): array
    {
        $result = [];
        foreach (array_keys($this->reasons->getTypeLabels()) as $type) {
            $result[$type] = $this->reasons->getOptions($type);
        }

        return $result;
    }

    public function getOriginLabel(string $origin): string
    {
        return $this->reasons->getOriginLabels()[$origin] ?? $origin;
    }

    /**
     * @return array<string, string>
     */
    public function getOriginLabels(): array
    {
        return $this->reasons->getOriginLabels();
    }

    /**
     * Qui rembourse le consommateur, et ce que fait MYCA, en une phrase.
     */
    public function getRefundRule(Rma $rma): string
    {
        return match ($rma->getOrigin()) {
            ReturnPolicy::ORIGIN_ONLINE => (string) __('Remboursement par MYCA sur le moyen de paiement d’origine.'),
            ReturnPolicy::ORIGIN_DROPSHIP => (string) __('Retour logistique par MYCA ; la boutique rembourse son client ; MYCA émet un avoir à la boutique.'),
            default => (string) __('Retour au comptoir de la boutique, sans remboursement MYCA ; avoir MYCA seulement si le défaut est reconnu.'),
        };
    }

    public function getConditionLabel(?string $condition): string
    {
        return $condition === null ? '' : ($this->reasons->getConditionLabels()[$condition] ?? $condition);
    }

    public function getOrderOrigin(Order $order): ?string
    {
        return $this->eligibility->getOrigin($order);
    }

    /**
     * @return string[]
     */
    public function getAllowedTypes(?string $origin): array
    {
        if ($origin === null) {
            return array_keys($this->reasons->getTypeLabels());
        }

        return $this->policy->getAllowedTypes($origin);
    }

    /**
     * @return array<int, float>
     */
    public function getReturnableQtys(Order $order): array
    {
        return $this->eligibility->getReturnableQtys($order);
    }

    /**
     * @return string[]
     */
    public function getBlockingReasons(Order $order): array
    {
        return $this->eligibility->getBlockingReasons($order);
    }

    public function getDeadline(Order $order, string $type = ReturnPolicy::TYPE_RETURN): ?\DateTimeImmutable
    {
        return $this->eligibility->getDeadline($order, $type);
    }

    public function getPolicyDays(Order $order, string $type = ReturnPolicy::TYPE_RETURN): int
    {
        return $this->eligibility->getPolicyDays($order, $type);
    }

    public function isDeadlinePassed(Order $order, string $type = ReturnPolicy::TYPE_RETURN): bool
    {
        $deadline = $this->getDeadline($order, $type);

        return $deadline !== null && $deadline < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Une commande peut-elle donner lieu à une demande (bouton « Demander un retour ») ?
     */
    public function canRequest(Order $order): bool
    {
        return !$this->getBlockingReasons($order) && !$this->isDeadlinePassed($order, ReturnPolicy::TYPE_WARRANTY);
    }

    /**
     * Demandes par commande, pour signaler « Retour en cours » dans la liste des commandes.
     *
     * @param Rma[] $rmas
     * @return array<int, Rma[]>
     */
    public function groupByOrder(array $rmas): array
    {
        $result = [];
        foreach ($rmas as $rma) {
            $result[$rma->getOrderId()][] = $rma;
        }

        return $result;
    }

    /**
     * Étapes de suivi (M23 « Suivi de la demande ») : parcours attendu selon le type, avec la date réelle de chaque
     * étape franchie. Une demande annulée ou refusée s'arrête à cette étape.
     *
     * @return array<int, array{label: string, state: string, date: ?string}> state : done, current, upcoming
     */
    public function getSteps(Rma $rma): array
    {
        $path = match ($rma->getType()) {
            ReturnPolicy::TYPE_EXCHANGE => [StatusFlow::CREATED, StatusFlow::AUTHORIZED, StatusFlow::IN_TRANSIT, StatusFlow::RECEIVED, StatusFlow::INSPECTED, StatusFlow::EXCHANGED, StatusFlow::CLOSED],
            ReturnPolicy::TYPE_WARRANTY => [StatusFlow::CREATED, StatusFlow::REVIEWING, StatusFlow::AUTHORIZED, StatusFlow::IN_TRANSIT, StatusFlow::RECEIVED, StatusFlow::INSPECTED, StatusFlow::CLOSED],
            default => [StatusFlow::CREATED, StatusFlow::AUTHORIZED, StatusFlow::IN_TRANSIT, StatusFlow::RECEIVED, StatusFlow::INSPECTED, StatusFlow::REFUNDED, StatusFlow::CLOSED],
        };
        $reached = [];
        foreach ($this->rmaResource->getHistory((int) $rma->getId(), false) as $entry) {
            $reached[(string) $entry['status']] ??= (string) $entry['created_at'];
        }
        $status = $rma->getStatus();
        if (!in_array($status, $path, true)) {
            $path = array_values(array_filter($path, static fn (string $step) => isset($reached[$step])));
            $path[] = $status;
        }
        $currentIndex = (int) array_search($status, $path, true);
        $labels = [
            StatusFlow::REFUNDED => $rma->getOrigin() === ReturnPolicy::ORIGIN_ONLINE ? (string) __('Remboursement émis') : (string) __('Avoir MYCA émis'),
        ];
        $steps = [];
        foreach ($path as $index => $step) {
            $isLast = $index === count($path) - 1;
            $steps[] = [
                'label' => $labels[$step] ?? $this->statusFlow->getLabel($step)['label'],
                'state' => $index < $currentIndex || ($index === $currentIndex && $isLast) ? 'done' : ($index === $currentIndex ? 'current' : 'upcoming'),
                'date' => $index <= $currentIndex ? ($reached[$step] ?? null) : null,
            ];
        }

        return $steps;
    }

    /**
     * Messages échangés (historique visible portant un texte).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(Rma $rma): array
    {
        return array_values(array_filter(
            $this->getHistory($rma),
            static fn (array $entry) => trim((string) $entry['message']) !== ''
        ));
    }

    public function getAuthorLabel(string $authorType): string
    {
        return match ($authorType) {
            'admin' => (string) __('Service client MYCA'),
            'partner' => (string) __('Boutique'),
            default => (string) __('Client'),
        };
    }
}
