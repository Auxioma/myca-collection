<?php

declare(strict_types=1);

namespace Myca\Content\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Plan du site (M51) : construit à partir des catégories et pages réellement publiées.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(private readonly PageFactory $pageFactory)
    {
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string) __('Plan du site'));
        $page->getConfig()->setDescription((string) __('Toutes les pages du site MYCA : collections, produits, aide, informations et pages légales.'));

        return $page;
    }
}
