<?php

declare(strict_types=1);

namespace Myca\Rma\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Helper\Guest as GuestHelper;
use Magento\Sales\Model\Order;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\PartnerData;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;

/**
 * Qui peut voir quoi :
 * - le client connecté, ses propres commandes ;
 * - le client sans compte, la commande retrouvée par le formulaire « Suivre ma commande » (référence, nom, e-mail) ;
 * - la boutique, les commandes et demandes de sa boutique seulement (cloisonnement Myca_Partner).
 */
class RequesterAccess
{
    /** @var array<int, Order|null> */
    private array $customerOrders = [];

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CurrentPartner $currentPartner,
        private readonly PartnerData $partnerData,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly GuestHelper $guestHelper,
        private readonly Registry $registry,
        private readonly RmaFactory $rmaFactory,
        private readonly RmaResource $rmaResource
    ) {
    }

    /**
     * Commande du client connecté, ou commande invité validée par le cookie Magento du formulaire de suivi.
     */
    public function getCustomerOrder(RequestInterface $request, int $orderId): ?Order
    {
        // Le contrôle invité enregistre la commande dans le registre : il ne peut être fait qu'une fois par requête.
        if (!array_key_exists($orderId, $this->customerOrders)) {
            $this->customerOrders[$orderId] = $this->findCustomerOrder($request, $orderId);
        }

        return $this->customerOrders[$orderId];
    }

    private function findCustomerOrder(RequestInterface $request, int $orderId): ?Order
    {
        if ($this->customerSession->isLoggedIn()) {
            $order = $this->loadOrder($orderId);

            return $order !== null && (int) $order->getCustomerId() === (int) $this->customerSession->getCustomerId()
                ? $order
                : null;
        }

        return $this->getGuestOrder($request, $orderId);
    }

    public function getPartnerOrder(int $orderId): ?Order
    {
        $partnerId = $this->currentPartner->getActiveId();

        return $partnerId === null ? null : $this->partnerData->getOrder($partnerId, $orderId);
    }

    public function getCustomerRma(RequestInterface $request, int $rmaId): ?Rma
    {
        $rma = $this->loadRma($rmaId);
        if ($rma === null || $rma->getPartnerId() !== null) {
            return null;
        }

        return $this->getCustomerOrder($request, $rma->getOrderId()) !== null ? $rma : null;
    }

    public function getPartnerRma(int $rmaId): ?Rma
    {
        $rma = $this->loadRma($rmaId);
        $partnerId = $this->currentPartner->getActiveId();

        return $rma !== null && $partnerId !== null && $rma->getPartnerId() === $partnerId ? $rma : null;
    }

    public function getAuthorName(?Order $order = null): string
    {
        if ($this->customerSession->isLoggedIn()) {
            return trim((string) $this->customerSession->getCustomer()->getName());
        }

        $name = $order === null ? '' : trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname());

        return $name !== '' ? $name : (string) __('Client');
    }

    public function getCustomerId(): ?int
    {
        return $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : null;
    }

    private function getGuestOrder(RequestInterface $request, int $orderId): ?Order
    {
        if ($this->guestHelper->loadValidOrder($request) !== true) {
            return null;
        }
        $order = $this->registry->registry('current_order');

        return $order instanceof Order && (int) $order->getId() === $orderId && !$order->getCustomerId() ? $order : null;
    }

    private function loadOrder(int $orderId): ?Order
    {
        if ($orderId <= 0) {
            return null;
        }
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return $order instanceof Order ? $order : null;
    }

    private function loadRma(int $rmaId): ?Rma
    {
        if ($rmaId <= 0) {
            return null;
        }
        $rma = $this->rmaFactory->create();
        $this->rmaResource->load($rma, $rmaId);

        return $rma->getId() ? $rma : null;
    }
}
