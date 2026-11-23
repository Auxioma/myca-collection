<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Parametres;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Controller\AbstractSave;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\NotificationPreferences;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;

/**
 * M26 : préférences de notification de l'utilisateur connecté.
 */
class Save extends AbstractSave
{
    protected const BACK_PATH = 'partenaire/parametres';

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        CustomerSession $customerSession,
        CurrentPartner $currentPartner,
        ManagerInterface $messageManager,
        private readonly PartnerResource $partnerResource,
        private readonly NotificationPreferences $notificationPreferences
    ) {
        parent::__construct($request, $redirectFactory, $customerSession, $currentPartner, $messageManager);
    }

    protected function save(PartnerInterface $partner, int $customerId): void
    {
        $submitted = (array) $this->request->getParam('notifications', []);
        $values = [];
        foreach (array_keys($this->notificationPreferences->getOptions()) as $code) {
            $values[$code] = !empty($submitted[$code]);
        }
        $this->partnerResource->saveUserPreferences($customerId, $values);
        $this->messageManager->addSuccessMessage(__('Vos paramètres ont été enregistrés.'));
    }
}
