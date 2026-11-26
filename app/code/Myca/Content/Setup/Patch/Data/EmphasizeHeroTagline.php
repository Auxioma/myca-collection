<?php

declare(strict_types=1);

namespace Myca\Content\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Identité typographique « Héritage moderne » : le titre de la bannière d'accueil passe en romain et seule
 * l'accroche « inspirée de nos voyages » reste en italique (balise em, stylée en or par le thème).
 * Ne modifie la page que si le titre d'origine est encore présent :
 * un texte retravaillé dans l'éditeur est conservé.
 */
class EmphasizeHeroTagline implements DataPatchInterface
{
    private const ORIGINAL = 'L’élégance sportive, inspirée de nos voyages';
    private const EMPHASIZED = 'L’élégance sportive, <em>inspirée de nos voyages</em>';
    private const HEADING = '#(<h1\b[^>]*\bmyca-pb-hero-title\b[^>]*>)(.*?)(</h1>)#su';

    public function __construct(
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    public function apply(): self
    {
        try {
            $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return $this;
        }
        $content = (string) $page->getContent();
        // Page Builder enregistre le texte avec des entités HTML (&rsquo;, &eacute;) : comparaison sur le texte décodé.
        $updated = preg_replace_callback(
            self::HEADING,
            static fn (array $m): string => html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') === self::ORIGINAL
                ? $m[1] . self::EMPHASIZED . $m[3]
                : $m[0],
            $content
        );
        if (is_string($updated) && $updated !== $content) {
            $page->setContent($updated);
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
