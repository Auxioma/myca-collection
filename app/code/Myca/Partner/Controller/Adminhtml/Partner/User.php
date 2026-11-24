<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml\Partner;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Partner\Controller\Adminhtml\AbstractAction;

/**
 * Rattache ou retire un compte (titulaire, collaborateur) d'une boutique.
 */
class User extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Partner::manage';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $partnerId = (int) $request->getParam('id');
        if ($request->getParam('operation') === 'remove') {
            return $this->run(
                fn () => $this->partnerManagement->removeUser($partnerId, (int) $request->getParam('customer_id')),
                (string) __('Le compte est retiré de la boutique.'),
                '*/partner/edit',
                ['id' => $partnerId]
            );
        }

        return $this->run(
            fn () => $this->partnerManagement->addUser($partnerId, (string) $request->getParam('email'), (string) $request->getParam('name'), (string) $request->getParam('role')),
            (string) __('Le compte est rattaché à la boutique. S’il vient d’être créé, il reçoit un e-mail pour définir son mot de passe.'),
            '*/partner/edit',
            ['id' => $partnerId]
        );
    }
}
