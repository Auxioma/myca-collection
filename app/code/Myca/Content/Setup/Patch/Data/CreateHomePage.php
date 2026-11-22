<?php

declare(strict_types=1);

namespace Myca\Content\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Myca\Catalog\Setup\Patch\Data\CreateCategoryTree;
use Myca\Catalog\Setup\Patch\Data\EnableBestSellerCondition;

/**
 * Page d'accueil (P01) au format Page Builder : chaque section reste modifiable dans l'éditeur
 * (Contenu > Pages > Accueil). Les photos sont celles du Figma, à remplacer par les visuels HD.
 */
class CreateHomePage implements DataPatchInterface
{
    private const MEDIA_DIRECTORY = 'wysiwyg/myca';

    public function __construct(
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly Filesystem $filesystem,
        private readonly File $file
    ) {
    }

    public function apply(): self
    {
        $this->copyMedia();

        $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        $page->setTitle('Accueil');
        $page->setContentHeading('');
        $page->setPageLayout('1column');
        $page->setContent($this->file->fileGetContents(__DIR__ . '/../../content/home.html'));
        $this->pageRepository->save($page);

        return $this;
    }

    private function copyMedia(): void
    {
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $media->create(self::MEDIA_DIRECTORY);

        $source = __DIR__ . '/../../content/media';
        foreach ($this->file->readDirectory($source) as $path) {
            $media->writeFile(self::MEDIA_DIRECTORY . '/' . basename($path), $this->file->fileGetContents($path));
        }
    }

    public static function getDependencies(): array
    {
        return [CreateCategoryTree::class, EnableBestSellerCondition::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
