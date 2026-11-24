<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Myca\Production\Model\ProductionManagement;

/**
 * Base des écrans de production : service, auteur pour l'historique, page et redirection vers la fiche.
 */
abstract class AbstractAction extends Action
{
    public function __construct(
        Context $context,
        protected readonly ProductionManagement $productionManagement,
        private readonly AuthSession $authSession
    ) {
        parent::__construct($context);
    }

    protected function getAuthor(): string
    {
        $user = $this->authSession->getUser();

        return $user ? trim($user->getFirstName() . ' ' . $user->getLastName()) : (string) __('Administration');
    }

    protected function page(string $menu, string $title): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu($menu);
        $page->getConfig()->getTitle()->prepend($title);

        return $page;
    }

    /**
     * Exécute une action sur un ordre et revient à sa fiche avec le message de succès ou d'erreur.
     */
    protected function onOrder(int $orderId, callable $action, string $success): Redirect
    {
        try {
            $action();
            $this->messageManager->addSuccessMessage($success);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/order/edit', ['id' => $orderId]);
    }
}
