<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Myca\Partner\Model\PartnerManagement;

/**
 * Base des écrans d'administration des partenaires : service, administrateur courant, page, redirection.
 */
abstract class AbstractAction extends Action
{
    public function __construct(
        Context $context,
        protected readonly PartnerManagement $partnerManagement,
        private readonly AuthSession $authSession
    ) {
        parent::__construct($context);
    }

    protected function getAdminUserId(): int
    {
        return (int) $this->authSession->getUser()?->getId();
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
     * Exécute une action et revient à la page indiquée, avec le message de succès ou d'erreur.
     *
     * @param array<string, mixed> $params
     */
    protected function run(callable $action, string $success, string $path, array $params = []): Redirect
    {
        try {
            $result = $action();
            $this->messageManager->addSuccessMessage($success);
            if (is_int($result) && $path === '*/partner/edit' && empty($params['id'])) {
                $params = ['id' => $result];
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath($path, $params);
    }
}
