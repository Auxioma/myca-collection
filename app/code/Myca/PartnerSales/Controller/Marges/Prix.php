<?php

declare(strict_types=1);

namespace Myca\PartnerSales\Controller\Marges;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Controller\AbstractSave;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;
use Myca\PartnerSales\Model\SalesData;

/**
 * « Ajuster mes prix de vente » : grille HT par modèle. Réservé au titulaire du compte boutique.
 */
class Prix extends AbstractSave
{
    protected const BACK_PATH = 'partenaire/marges/index/vue/prix';

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        CustomerSession $customerSession,
        CurrentPartner $currentPartner,
        ManagerInterface $messageManager,
        private readonly SalesData $salesData,
        private readonly PartnerResource $partnerResource
    ) {
        parent::__construct($request, $redirectFactory, $customerSession, $currentPartner, $messageManager);
    }

    protected function save(PartnerInterface $partner, int $customerId): void
    {
        if ($this->partnerResource->getUserRole((int) $partner->getId(), $customerId) !== 'owner') {
            $this->messageManager->addErrorMessage(__('Seul le titulaire du compte boutique peut modifier les prix de vente.'));

            return;
        }
        try {
            $this->salesData->savePrices((int) $partner->getId(), (array) $this->request->getParam('price', []));
            $this->messageManager->addSuccessMessage(__('Vos prix de vente sont enregistrés. Les marges sont recalculées.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }
}
