<?php

declare(strict_types=1);

namespace Myca\Content\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Retire de la page d'accueil les règles de style Page Builder dont l'élément n'existe plus
 * (« [data-pb-style=…] » d'une rangée remplacée). L'éditeur Page Builder ne s'ouvre pas tant qu'une règle
 * vise un élément absent (stage-builder.js : setAttribute sur null).
 */
class PruneHomeOrphanStyles implements DataPatchInterface
{
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
        $cleaned = self::prune($content);
        if ($cleaned !== $content) {
            $page->setContent($cleaned);
            $this->pageRepository->save($page);
        }

        return $this;
    }

    /**
     * Supprime, dans les blocs <style> du contenu, les sélecteurs [data-pb-style=ID] sans élément correspondant,
     * puis les règles et blocs @media devenus vides.
     */
    public static function prune(string $content): string
    {
        $html = (string) preg_replace('#<style\b[^>]*>.*?</style>#s', '', $content);
        preg_match_all('/\bdata-pb-style="([^"]+)"/', $html, $matches);
        $existing = array_flip($matches[1]);

        return (string) preg_replace_callback(
            '#(<style\b[^>]*>)(.*?)(</style>)#s',
            static function (array $m) use ($existing): string {
                $css = self::pruneCss($m[2], $existing);

                return trim($css) === '' ? '' : $m[1] . $css . $m[3];
            },
            $content
        );
    }

    /**
     * @param array<string, int> $existing
     */
    private static function pruneCss(string $css, array $existing): string
    {
        $out = '';
        $length = strlen($css);
        $i = 0;
        while ($i < $length) {
            $open = strpos($css, '{', $i);
            if ($open === false) {
                break;
            }
            $prelude = substr($css, $i, $open - $i);
            $close = self::matchingBrace($css, $open);
            $body = substr($css, $open + 1, $close - $open - 1);
            $i = $close + 1;
            if (str_starts_with(trim($prelude), '@')) {
                $inner = self::pruneCss($body, $existing);
                if (trim($inner) !== '') {
                    $out .= $prelude . '{' . $inner . '}';
                }
                continue;
            }
            $selectors = array_filter(
                array_map('trim', explode(',', $prelude)),
                static function (string $selector) use ($existing): bool {
                    if (!preg_match_all('/data-pb-style=["\']?([A-Za-z0-9_-]+)/', $selector, $ids)) {
                        return $selector !== '';
                    }
                    foreach ($ids[1] as $id) {
                        if (!isset($existing[$id])) {
                            return false;
                        }
                    }

                    return true;
                }
            );
            if ($selectors !== []) {
                $out .= implode(',', $selectors) . '{' . $body . '}';
            }
        }

        return $out;
    }

    private static function matchingBrace(string $css, int $open): int
    {
        $depth = 0;
        $length = strlen($css);
        for ($j = $open; $j < $length; $j++) {
            if ($css[$j] === '{') {
                $depth++;
            } elseif ($css[$j] === '}' && --$depth === 0) {
                return $j;
            }
        }

        return $length - 1;
    }

    public static function getDependencies(): array
    {
        return [ReworkHomeLowerSection::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
