<?php

declare(strict_types=1);

namespace Myca\Content\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sitemap\Model\ResourceModel\Sitemap\CollectionFactory;
use Magento\Sitemap\Model\SitemapFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Indexation : sitemap XML par vue de magasin (régénéré chaque jour par le cron et déclaré dans robots.txt), et
 * robots.txt qui écarte comptes, panier, tunnel, recherche interne et espaces privés.
 */
class ConfigureSeo implements DataPatchInterface
{
    private const ROBOTS = "User-agent: *\n"
        . "Disallow: /checkout/\nDisallow: /customer/\nDisallow: /sales/\nDisallow: /wishlist/\n"
        . "Disallow: /catalogsearch/\nDisallow: /partenaire/\nDisallow: /retours/\nDisallow: /devis/\n"
        . "Disallow: /fidelite/\nDisallow: /candidature-partenaire/\nDisallow: /*?*SID=\n";

    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly SitemapFactory $sitemapFactory,
        private readonly CollectionFactory $sitemapCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        $this->configWriter->save('design/search_engine_robots/default_robots', 'INDEX,FOLLOW');
        $this->configWriter->save('design/search_engine_robots/custom_instructions', self::ROBOTS);
        $this->configWriter->save('sitemap/generate/enabled', '1');
        $this->configWriter->save('sitemap/search_engines/submission_robots', '1');
        foreach ($this->storeManager->getStores() as $store) {
            $existing = $this->sitemapCollectionFactory->create()->addStoreFilter([(int) $store->getId()])->getSize();
            if ($existing > 0) {
                continue;
            }
            $sitemap = $this->sitemapFactory->create();
            $sitemap->setData([
                'sitemap_filename' => count($this->storeManager->getStores()) > 1 ? 'sitemap-' . $store->getCode() . '.xml' : 'sitemap.xml',
                'sitemap_path' => '/',
                'store_id' => (int) $store->getId(),
            ]);
            $sitemap->save();
        }

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
