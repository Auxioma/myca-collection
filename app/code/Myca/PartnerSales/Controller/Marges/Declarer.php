<?php

declare(strict_types=1);

namespace Myca\PartnerSales\Controller\Marges;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Myca\Dropship\Model\ResourceModel\DropshipOrder;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Controller\AbstractSave;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\PartnerData;

/**
 * Déclare (ou efface) le prix de vente HT d'une commande en dropshipping de la boutique.
 */
class Declarer extends AbstractSave
{
    protected const BACK_PATH = 'partenaire/marges/index/vue/ventes';

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        CustomerSession $customerSession,
        CurrentPartner $currentPartner,
        ManagerInterface $messageManager,
        private readonly PartnerData $partnerData,
        private readonly DropshipOrder $dropshipOrder
    ) {
        parent::__construct($request, $redirectFactory, $customerSession, $currentPartner, $messageManager);
    }

    protected function save(PartnerInterface $partner, int $customerId): void
    {
        $partnerId = (int) $partner->getId();
        $orderId = (int) $this->request->getParam('order_id');
        $mode = $this->dropshipOrder->getForPartner($partnerId)[$orderId]['mode'] ?? null;
        if (!$this->partnerData->ownsOrder($partnerId, $orderId) || $mode !== DropshipOrder::MODE_DROPSHIP) {
            $this->messageManager->addErrorMessage(__('Cette commande est introuvable.'));

            return;
        }
        $value = str_replace([' ', ','], ['', '.'], trim((string) $this->request->getParam('amount')));
        if ($value !== '' && (!is_numeric($value) || (float) $value <= 0 || (float) $value > 1000000)) {
            $this->messageManager->addErrorMessage(__('Indiquez un montant HT positif, ou laissez vide.'));

            return;
        }
        $this->dropshipOrder->save($orderId, $partnerId, DropshipOrder::MODE_DROPSHIP, $value === '' ? null : round((float) $value, 2));
        $this->messageManager->addSuccessMessage(__('Le prix de vente de la commande est enregistré.'));
    }
}
