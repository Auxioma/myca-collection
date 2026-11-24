<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml\Partner;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Partner\Controller\Adminhtml\AbstractAction;

/**
 * Création (boutique active + compte du titulaire) ou modification des informations d'une boutique.
 */
class Save extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Partner::manage';

    public function execute(): Redirect
    {
        $partnerId = (int) $this->getRequest()->getParam('id');
        $data = (array) $this->getRequest()->getParam('partner', []);
        if ($partnerId > 0) {
            return $this->run(
                fn () => $this->partnerManagement->updatePartner($partnerId, $data),
                (string) __('La boutique est enregistrée.'),
                '*/partner/edit',
                ['id' => $partnerId]
            );
        }

        return $this->run(
            fn () => $this->partnerManagement->createFromAdmin($data),
            (string) __('Boutique créée et activée. Le titulaire reçoit un e-mail pour définir son mot de passe.'),
            '*/partner/edit'
        );
    }
}
