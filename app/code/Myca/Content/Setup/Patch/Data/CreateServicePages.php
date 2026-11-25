<?php

declare(strict_types=1);

namespace Myca\Content\Setup\Patch\Data;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Pages de service et pages juridiques liées au pied de page (M41, M43, M44, M46, M48, M49, M52…), au format
 * Page Builder : chaque page reste modifiable dans Contenu > Pages. Les données réelles de MYCA (raison sociale,
 * hébergeur, médiateur…) sont signalées « [À COMPLÉTER] » et les textes juridiques restent à faire valider.
 * Une page existante portant la même adresse n'est jamais écrasée.
 */
class CreateServicePages implements DataPatchInterface
{
    /**
     * identifiant => [titre, titre affiché, description]
     */
    public const PAGES = [
        'aide' => ['Aide & services', 'Aide & services', 'Suivi de commande, livraison, retours, paiement et contact du service client MYCA.'],
        'faq' => ['Questions fréquentes', 'Questions fréquentes', 'Réponses sur la commande, le paiement, la livraison, les retours, les tailles et l’authenticité MYCA.'],
        'livraison' => ['Livraison', 'Livraison', 'Modes de livraison MYCA, destinations desservies et point relais offert dès 100 € d’achats éligibles.'],
        'retours-echanges' => ['Retours & échanges', 'Retours & échanges', 'Droit de rétractation, échanges de taille, produits défectueux : comment retourner un article MYCA.'],
        'nos-engagements' => ['Nos engagements', 'Nos engagements', 'Qualité contrôlée, traçabilité, paiement sécurisé et retours simples : les engagements MYCA.'],
        'notre-histoire' => ['Notre histoire', 'Notre histoire', 'MYCA, marque française de sportswear raffiné née du voyage.'],
        'qualite-matieres' => ['Qualité & matières', 'Qualité & matières', 'Matières, contrôle qualité et entretien des vêtements MYCA.'],
        'passeport-du-vetement' => ['Passeport du vêtement', 'Passeport du vêtement', 'Vérifiez l’authenticité d’une pièce MYCA grâce à son passeport et à son QR code.'],
        'cgv' => ['Conditions générales de vente', 'Conditions générales de vente', 'Conditions générales de vente MYCA applicables aux consommateurs.'],
        'cgu' => ['Conditions générales d’utilisation', 'Conditions générales d’utilisation', 'Conditions d’utilisation du site MYCA, de l’espace client et de l’espace partenaire.'],
        'confidentialite' => ['Politique de confidentialité', 'Politique de confidentialité', 'Comment MYCA utilise et protège vos données personnelles, et comment exercer vos droits.'],
        'cookies' => ['Politique cookies', 'Politique cookies', 'Cookies utilisés sur le site MYCA et gestion de votre consentement.'],
        'mentions-legales' => ['Mentions légales', 'Mentions légales', 'Éditeur, hébergeur et propriété intellectuelle du site MYCA.'],
    ];

    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly File $file
    ) {
    }

    public function apply(): self
    {
        foreach (self::PAGES as $identifier => [$title, $heading, $description]) {
            $page = $this->pageFactory->create();
            if ($page->checkIdentifier($identifier, Store::DEFAULT_STORE_ID)) {
                continue;
            }
            $page->setData([
                'identifier' => $identifier,
                'title' => $title,
                'content_heading' => $heading,
                'page_layout' => '1column',
                'is_active' => 1,
                'stores' => [Store::DEFAULT_STORE_ID],
                'meta_title' => $title . ' | MYCA',
                'meta_description' => $description,
                'meta_keywords' => '',
                'content' => $this->file->fileGetContents(__DIR__ . '/../../content/pages/' . $identifier . '.html'),
            ]);
            $this->pageRepository->save($page);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateHomePage::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
