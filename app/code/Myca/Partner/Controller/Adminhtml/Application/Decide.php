<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml\Application;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Partner\Controller\Adminhtml\AbstractAction;

/**
 * Accepter (création de la boutique et du compte titulaire) ou refuser (avec motif) une candidature.
 */
class Decide extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Partner::applications';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $applicationId = (int) $request->getParam('id');
        if ($request->getParam('decision') === 'approve') {
            return $this->run(
                fn () => $this->partnerManagement->approveApplication($applicationId, $this->getAdminUserId()),
                (string) __('Candidature acceptée : boutique créée et activée. Le titulaire reçoit un e-mail pour définir son mot de passe.'),
                '*/partner/edit'
            );
        }

        return $this->run(
            fn () => $this->partnerManagement->rejectApplication($applicationId, (string) $request->getParam('reason'), $this->getAdminUserId()),
            (string) __('Candidature refusée.'),
            '*/application/view',
            ['id' => $applicationId]
        );
    }
}
