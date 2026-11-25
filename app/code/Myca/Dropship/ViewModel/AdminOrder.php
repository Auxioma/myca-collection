<?php

declare(strict_types=1);

namespace Myca\Dropship\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Myca\Dropship\Model\ResourceModel\DropshipOrder;
use Myca\Partner\Api\PartnerRepositoryInterface;

/**
 * Fiche commande (administration) : canal de vente partenaire, boutique, destinataire et bon de livraison sans prix.
 */
class AdminOrder implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly DropshipOrder $dropshipOrder,
        private readonly PartnerRepositoryInterface $partnerRepository,
        private readonly UrlInterface $backendUrl
    ) {
    }

    public function getOrder(): ?Order
    {
        $order = $this->registry->registry('current_order');

        return $order instanceof Order ? $order : null;
    }

    /**
     * @return array{mode: string, company: string, code: string, partner_url: string, declared: float|null, slip_url: string}|null
     */
    public function getPartnerInfo(Order $order): ?array
    {
        $info = $this->dropshipOrder->getForOrder((int) $order->getId());
        if ($info === null) {
            return null;
        }
        try {
            $partner = $this->partnerRepository->getById($info['partner_id']);
        } catch (NoSuchEntityException) {
            return null;
        }

        return [
            'mode' => $info['mode'],
            'company' => $partner->getCompanyName(),
            'code' => $partner->getCode(),
            'partner_url' => $this->backendUrl->getUrl('myca_partner/partner/edit', ['id' => $info['partner_id']]),
            'declared' => $info['declared_sale_amount'],
            'slip_url' => $this->backendUrl->getUrl('myca_dropship/packingslip/index', ['order_id' => (int) $order->getId()]),
        ];
    }

    /**
     * @return array<string, string> coordonnées de la boutique, vendeuse au client final (dropshipping)
     */
    public function getSellerContact(int $partnerId): array
    {
        try {
            $partner = $this->partnerRepository->getById($partnerId);
        } catch (NoSuchEntityException) {
            return [];
        }
        $get = static fn (string $key): string => $partner instanceof AbstractModel ? trim((string) $partner->getData($key)) : '';
        $hidden = $get('hide_contact_details') === '1';

        return [
            'company' => $partner->getCompanyName(),
            'address' => trim($get('street') . ', ' . $get('postcode') . ' ' . $get('city'), ', '),
            'phone' => $hidden ? '' : $get('phone'),
            'email' => $hidden ? '' : $get('email'),
        ];
    }
}
