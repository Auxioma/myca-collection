<?php

declare(strict_types=1);

namespace Myca\Content\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Le titre de la bannière contient une accroche en italique (balise em). L'élément « Titre » de Page Builder
 * n'accepte que du texte brut et afficherait la balise : le titre devient un élément « Texte » contenant un h1,
 * modifiable dans l'éditeur (bouton italique) sans perdre la mise en forme.
 */
class ConvertHeroTitleToText implements DataPatchInterface
{
    private const HEADING = '#<h1\b([^>]*\bmyca-pb-hero-title\b[^>]*)>(.*?)</h1>#s';

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
        $updated = preg_replace_callback(
            self::HEADING,
            static function (array $m): string {
                if (!str_contains($m[1], 'data-content-type="heading"')) {
                    return $m[0];
                }
                $style = preg_match('/\sdata-pb-style="[^"]*"/', $m[1], $s) ? $s[0] : '';

                return '<div class="myca-pb-hero-title" data-content-type="text" data-appearance="default"'
                    . ' data-element="main"' . $style . '><h1>' . $m[2] . '</h1></div>';
            },
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
        return [EmphasizeHeroTagline::class, PruneHomeOrphanStyles::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
