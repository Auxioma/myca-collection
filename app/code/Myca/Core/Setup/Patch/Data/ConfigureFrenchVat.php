<?php

declare(strict_types=1);

namespace Myca\Core\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\Data\TaxRuleInterfaceFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Magento\Tax\Model\ResourceModel\Calculation\Rate\CollectionFactory as RateCollectionFactory;

/**
 * Boutique en prix TTC (France) : prix catalogue saisis et affichés TTC, remise appliquée sur le TTC
 * (seuil « 100 € TTC après remise »), TVA française à 20 % pour les produits taxables.
 * Taux et classes de taxe à valider par le comptable avant la mise en production.
 */
class ConfigureFrenchVat implements DataPatchInterface
{
    private const RATE_CODE = 'FR-TVA-20';

    private const CONFIG = [
        'tax/calculation/price_includes_tax' => 1,
        'tax/calculation/shipping_includes_tax' => 1,
        'tax/calculation/discount_tax' => 1,
        'tax/calculation/apply_after_discount' => 1,
        'tax/defaults/country' => 'FR',
        // Origine France : sans elle, Magento considère les prix TTC comme hors taxe et ajoute la TVA par-dessus.
        'shipping/origin/country_id' => 'FR',
        'tax/display/type' => 2,
        'tax/display/shipping' => 2,
        'tax/cart_display/price' => 2,
        'tax/cart_display/subtotal' => 2,
        'tax/cart_display/shipping' => 2,
        'tax/cart_display/grandtotal' => 1,
        'tax/sales_display/price' => 2,
        'tax/sales_display/subtotal' => 2,
        'tax/sales_display/shipping' => 2,
        'tax/sales_display/grandtotal' => 1,
    ];

    /** Classes de taxe créées par l'installation Magento : « Retail Customer » (3) et « Taxable Goods » (2). */
    private const CUSTOMER_TAX_CLASS = 3;
    private const PRODUCT_TAX_CLASS = 2;

    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly TaxRateRepositoryInterface $taxRateRepository,
        private readonly TaxRateInterfaceFactory $taxRateFactory,
        private readonly TaxRuleRepositoryInterface $taxRuleRepository,
        private readonly TaxRuleInterfaceFactory $taxRuleFactory,
        private readonly RateCollectionFactory $rateCollectionFactory
    ) {
    }

    public function apply(): self
    {
        foreach (self::CONFIG as $path => $value) {
            $this->configWriter->save($path, (string) $value);
        }

        if (!$this->rateCollectionFactory->create()->addFieldToFilter('code', self::RATE_CODE)->getSize()) {
            $rate = $this->taxRateFactory->create()
                ->setCode(self::RATE_CODE)
                ->setTaxCountryId('FR')
                ->setTaxPostcode('*')
                ->setRate(20.0);
            $rate = $this->taxRateRepository->save($rate);

            $rule = $this->taxRuleFactory->create()
                ->setCode('TVA France 20 %')
                ->setTaxRateIds([(int) $rate->getId()])
                ->setCustomerTaxClassIds([self::CUSTOMER_TAX_CLASS])
                ->setProductTaxClassIds([self::PRODUCT_TAX_CLASS])
                ->setPriority(0)
                ->setPosition(0);
            $this->taxRuleRepository->save($rule);
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
