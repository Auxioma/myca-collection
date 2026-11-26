<?php

declare(strict_types=1);

namespace Myca\Content\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Bas de l'accueil retravaillé : encarts « MYCA Signature » et « Vous êtes une boutique ? » en photo pleine
 * largeur avec surtitre et atouts, nouveau texte de la newsletter.
 * Les rangées restent des éléments Page Builder modifiables. Sans effet si les encarts sont déjà à jour.
 */
class ReworkHomeLowerSection implements DataPatchInterface
{
    private const NEWSLETTER_TEXT = 'Soyez les premiers informés de nos nouveautés, offres exclusives et événements.';

    private const STYLES = '#html-body [data-pb-style=MYCAPRI0]{display:flex;flex-direction:column}'
        . '#html-body [data-pb-style=MYCAPRG0]{display:flex;width:100%}'
        . '#html-body [data-pb-style=MYCAPRC1],#html-body [data-pb-style=MYCAPRC2]'
        . '{justify-content:flex-start;display:flex;flex-direction:column;width:50%;align-self:stretch}';

    private const PROMO_ROW = <<<'HTML'
<div data-content-type="row" data-appearance="contained" data-element="main"><div class="myca-pb-section" data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}" data-background-type="image" data-video-loop="true" data-video-play-only-visible="true" data-video-lazy-load="true" data-video-fallback-src="" data-element="inner" data-pb-style="MYCAPRI0"><div class="pagebuilder-column-group" data-background-images="{}" data-content-type="column-group" data-appearance="default" data-grid-size="12" data-element="main"><div class="pagebuilder-column-line" data-content-type="column-line" data-grid-size="12" data-element="main" data-pb-style="MYCAPRG0"><div class="pagebuilder-column myca-pb-promo" data-content-type="column" data-appearance="full-height" data-background-images="{}" data-element="main" data-pb-style="MYCAPRC1"><div class="myca-pb-kicker" data-content-type="text" data-appearance="default" data-element="main"><p>Collection MYCA</p></div><h2 class="myca-pb-promo-title" data-content-type="heading" data-appearance="default" data-element="main">MYCA Signature</h2><div data-content-type="text" data-appearance="default" data-element="main"><p>Nos modèles iconiques, sélectionnés et réédités par MYCA.</p></div><div data-content-type="buttons" data-appearance="inline" data-same-width="false" data-element="main"><div class="myca-pb-pill" data-content-type="button-item" data-appearance="default" data-element="main"><a class="pagebuilder-button-primary" href="{{store url='myca-signature.html'}}" target="" data-link-type="default" data-element="link"><span data-element="link_text">Découvrir la ligne</span></a></div></div><div class="myca-pb-promo-features" data-content-type="text" data-appearance="default" data-element="main"><ul><li class="myca-pb-icon-gem">Qualité premium</li><li class="myca-pb-icon-shirt">Style intemporel</li><li class="myca-pb-icon-star">L’élégance sportive</li></ul></div><figure data-content-type="image" data-appearance="full-width" data-element="main"><img class="pagebuilder-mobile-hidden" src="{{media url=wysiwyg/myca/accueil-signature.webp}}" alt="Polo MYCA Signature porté" title="" data-element="desktop_image"><img class="pagebuilder-mobile-only" src="{{media url=wysiwyg/myca/accueil-signature.webp}}" alt="Polo MYCA Signature porté" title="" data-element="mobile_image"></figure></div><div class="pagebuilder-column myca-pb-promo myca-pb-promo-light" data-content-type="column" data-appearance="full-height" data-background-images="{}" data-element="main" data-pb-style="MYCAPRC2"><div class="myca-pb-kicker" data-content-type="text" data-appearance="default" data-element="main"><p>Boutique MYCA</p></div><h2 class="myca-pb-promo-title" data-content-type="heading" data-appearance="default" data-element="main">Vous êtes une boutique ?</h2><div data-content-type="text" data-appearance="default" data-element="main"><p>Vendez MYCA en dropshipping ou en achat de stock, au tarif partenaire.</p></div><div data-content-type="buttons" data-appearance="inline" data-same-width="false" data-element="main"><div class="myca-pb-pill" data-content-type="button-item" data-appearance="default" data-element="main"><a class="pagebuilder-button-primary" href="{{store url='devenir-partenaire'}}" target="" data-link-type="default" data-element="link"><span data-element="link_text">Devenir partenaire</span></a></div></div><figure data-content-type="image" data-appearance="full-width" data-element="main"><img class="pagebuilder-mobile-hidden" src="{{media url=wysiwyg/myca/accueil-boutique.webp}}" alt="Boutique partenaire MYCA" title="" data-element="desktop_image"><img class="pagebuilder-mobile-only" src="{{media url=wysiwyg/myca/accueil-boutique.webp}}" alt="Boutique partenaire MYCA" title="" data-element="mobile_image"></figure></div></div></div></div></div>
HTML;

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
        if (str_contains($content, 'myca-pb-kicker')) {
            return $this;
        }
        $rows = preg_split('/(?=<div data-content-type="row")/', $content) ?: [$content];
        $changed = false;
        foreach ($rows as $index => $row) {
            if (str_contains($row, 'myca-pb-promo')) {
                $rows[$index] = self::PROMO_ROW;
                $changed = true;
            } elseif (str_contains($row, 'Block\Widget\Newsletter')) {
                $row = (string) preg_replace('/\btext="[^"]*"/', 'text="' . self::NEWSLETTER_TEXT . '"', $row, 1);
                $rows[$index] = $row;
                $changed = true;
            }
        }
        if (!$changed) {
            return $this;
        }
        $content = implode('', $rows);
        $position = strpos($content, '</style>');
        $content = $position === false
            ? '<style>' . self::STYLES . '</style>' . $content
            : substr_replace($content, self::STYLES, $position, 0);
        $page->setContent($content);
        $this->pageRepository->save($page);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateHomePage::class, EmphasizeHeroTagline::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
