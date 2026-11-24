<?php

declare(strict_types=1);

namespace Myca\Partner\Setup\Patch\Data;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Page CMS « Conditions de vente professionnelles » (Page Builder), liée à la case du formulaire « Devenir
 * partenaire ». Modèle à compléter et à faire valider ; modifiable dans Contenu > Pages. Une page existante portant
 * la même adresse n'est jamais écrasée.
 */
class CreateB2bTermsPage implements DataPatchInterface
{
    public const IDENTIFIER = 'conditions-vente-professionnelles';

    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly File $file
    ) {
    }

    public function apply(): self
    {
        $page = $this->pageFactory->create();
        if ($page->checkIdentifier(self::IDENTIFIER, Store::DEFAULT_STORE_ID)) {
            return $this;
        }
        $page->setData([
            'identifier' => self::IDENTIFIER,
            'title' => 'Conditions de vente professionnelles',
            'content_heading' => 'Conditions de vente professionnelles (B2B)',
            'page_layout' => '1column',
            'is_active' => 1,
            'stores' => [Store::DEFAULT_STORE_ID],
            'meta_title' => 'Conditions de vente professionnelles | MYCA',
            'meta_description' => 'Conditions de vente applicables aux boutiques partenaires MYCA : commandes, prix, paiement, livraison, retours.',
            'content' => $this->file->fileGetContents(__DIR__ . '/../../content/conditions-vente-professionnelles.html'),
        ]);
        $this->pageRepository->save($page);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
