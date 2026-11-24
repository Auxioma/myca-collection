<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Techpack;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Myca\Production\Model\TechpackManagement;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::techpacks';

    public function __construct(
        Context $context,
        private readonly TechpackManagement $techpackManagement,
        private readonly AuthSession $authSession
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $productId = (int) $this->getRequest()->getParam('product_id');
        $user = $this->authSession->getUser();
        try {
            $this->techpackManagement->save(
                $productId,
                (array) $this->getRequest()->getParam('techpack', []),
                $user ? trim($user->getFirstName() . ' ' . $user->getLastName()) : (string) __('Administration')
            );
            $this->messageManager->addSuccessMessage(__('La fiche technique est enregistrée.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/', ['product_id' => $productId]);
    }
}
