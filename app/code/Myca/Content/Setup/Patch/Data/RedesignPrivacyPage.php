<?php

declare(strict_types=1);

namespace Myca\Content\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Page « Politique de confidentialité » présentée d’après la maquette : bannière, sommaire et un article par carte, texte juridique inchangé.
 * Le titre passe dans la bannière (titre de page masqué). Une page déjà retouchée dans l'admin n'est pas remplacée.
 */
class RedesignPrivacyPage implements DataPatchInterface
{
    private const IDENTIFIER = 'confidentialite';

    /** Marqueur du contenu d'origine (patch CreateServicePages). */
    private const ORIGINAL_MARKER = '<h2>Responsable du traitement</h2>';

    public function __construct(
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly File $file
    ) {
    }

    public function apply(): self
    {
        try {
            $page = $this->getPageByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return $this;
        }
        $content = (string) $page->getContent();
        if (str_contains($content, 'myca-doc-article') || !str_contains($content, self::ORIGINAL_MARKER)) {
            return $this;
        }
        $page->setContent($this->file->fileGetContents(__DIR__ . '/../../content/pages/' . self::IDENTIFIER . '.html'));
        $page->setContentHeading('');
        $this->pageRepository->save($page);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateServicePages::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
