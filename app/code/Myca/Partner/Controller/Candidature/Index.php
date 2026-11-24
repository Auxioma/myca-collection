<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Candidature;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * P17 — « Devenir partenaire » : présentation des deux modèles et formulaire de candidature (page publique).
 */
class Index implements HttpGetActionInterface
{
    public function __construct(private readonly PageFactory $pageFactory)
    {
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string) __('Devenir partenaire MYCA'));
        $page->getConfig()->setDescription((string) __('Vendez MYCA dans votre boutique : dropshipping ou achat de stock au tarif professionnel.'));

        return $page;
    }
}
