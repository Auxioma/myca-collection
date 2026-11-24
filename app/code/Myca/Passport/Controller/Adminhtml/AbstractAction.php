<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Myca\Passport\Model\PassportManagement;

/**
 * Base des actions d'administration des passeports : service et nom de l'auteur pour l'historique.
 */
abstract class AbstractAction extends Action
{
    public function __construct(
        Context $context,
        protected readonly PassportManagement $passportManagement,
        private readonly AuthSession $authSession
    ) {
        parent::__construct($context);
    }

    protected function getAuthor(): string
    {
        $user = $this->authSession->getUser();

        return $user ? trim($user->getFirstName() . ' ' . $user->getLastName()) : (string) __('Administration');
    }
}
