<?php

declare(strict_types=1);

namespace Myca\Rma\ViewModel;

use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Myca\Partner\Api\PartnerRepositoryInterface;
use Myca\Rma\Controller\Adminhtml\Rma\View as ViewController;
use Myca\Rma\Model\Reasons;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;
use Myca\Rma\Model\ReturnPolicy;
use Myca\Rma\Model\Rma;
use Myca\Rma\Model\StatusFlow;

/**
 * Fiche de traitement d'une demande dans l'administration.
 */
class Admin implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Returns $returns,
        private readonly RmaResource $rmaResource,
        private readonly StatusFlow $statusFlow,
        private readonly ReturnPolicy $policy,
        private readonly Reasons $reasons,
        private readonly PartnerRepositoryInterface $partnerRepository,
        private readonly UrlInterface $url
    ) {
    }

    public function getRma(): ?Rma
    {
        $rma = $this->registry->registry(ViewController::REGISTRY_KEY);

        return $rma instanceof Rma ? $rma : null;
    }

    public function getReturns(): Returns
    {
        return $this->returns;
    }

    public function getOrder(Rma $rma): ?Order
    {
        return $this->returns->getOrder($rma);
    }

    /**
     * Historique complet, notes internes comprises.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(Rma $rma): array
    {
        return $this->rmaResource->getHistory((int) $rma->getId(), false);
    }

    /**
     * États proposés au service client. Le contrôle et le remboursement ont leurs propres formulaires.
     *
     * @return array<string, string>
     */
    public function getStatusOptions(Rma $rma): array
    {
        $options = [];
        foreach ($this->statusFlow->getAllowedTransitions($rma->getStatus(), StatusFlow::ACTOR_ADMIN) as $status) {
            if ($status === StatusFlow::INSPECTED) {
                continue;
            }
            if ($status === StatusFlow::EXCHANGED && $rma->getType() !== ReturnPolicy::TYPE_EXCHANGE) {
                continue;
            }
            $options[$status] = $this->statusFlow->getLabel($status)['label'];
        }

        return $options;
    }

    public function canInspect(Rma $rma): bool
    {
        return $rma->getStatus() === StatusFlow::RECEIVED;
    }

    public function canRefund(Rma $rma): bool
    {
        return $rma->getCreditmemoId() === null
            && $this->statusFlow->canRefund($rma->getStatus())
            && $this->policy->issuesMycaCredit($rma->getOrigin(), $rma->getType());
    }

    /**
     * @return array<string, string>
     */
    public function getConditionOptions(): array
    {
        return $this->reasons->getConditionLabels();
    }

    public function getPartnerName(Rma $rma): string
    {
        $partnerId = $rma->getPartnerId();
        if ($partnerId === null) {
            return '';
        }
        try {
            $partner = $this->partnerRepository->getById($partnerId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            return '';
        }

        return $partner->getCompanyName() . ' (' . $partner->getCode() . ')';
    }

    public function getRefundPartyLabel(Rma $rma): string
    {
        return match ((string) $rma->getData('refund_party')) {
            ReturnPolicy::PARTY_MYCA => (string) __('MYCA rembourse le payeur de la commande'),
            ReturnPolicy::PARTY_PARTNER => (string) __('La boutique rembourse son client ; MYCA émet un avoir à la boutique'),
            default => (string) __('Aucun remboursement MYCA au client final (retour au comptoir de la boutique)'),
        };
    }

    public function getOrderUrl(Rma $rma): string
    {
        return $this->url->getUrl('sales/order/view', ['order_id' => $rma->getOrderId()]);
    }

    public function getCreditmemoUrl(Rma $rma): string
    {
        return $this->url->getUrl('sales/creditmemo/view', ['creditmemo_id' => $rma->getCreditmemoId()]);
    }

    public function getProcessUrl(): string
    {
        return $this->url->getUrl('myca_rma/rma/process');
    }

    public function getBackUrl(): string
    {
        return $this->url->getUrl('myca_rma/rma/index');
    }
}
