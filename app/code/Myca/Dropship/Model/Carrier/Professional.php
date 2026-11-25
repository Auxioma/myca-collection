<?php

declare(strict_types=1);

namespace Myca\Dropship\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Myca\Dropship\Model\OrderConfig;
use Myca\Dropship\Model\PartnerPricing;
use Myca\Dropship\Model\QuotePartner;
use Myca\Shipping\Model\Config as ShippingConfig;
use Psr\Log\LoggerInterface;

/**
 * « MYCA – Livraison professionnelle » : barème B2B distinct du B2C, proposé uniquement aux boutiques actives.
 * Dropshipping : expédition au client final ; achat de stock : livraison à la boutique (port offert au-delà d'un
 * montant). Seules les destinations desservies à domicile sont acceptées.
 */
class Professional extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'mycapro';

    /** @var string */
    protected $_code = self::CODE;

    /** @var bool */
    protected $_isFixed = true;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly QuotePartner $quotePartner,
        private readonly OrderConfig $orderConfig,
        private readonly ShippingConfig $shippingConfig,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        $items = $request->getAllItems() ?: [];
        $first = reset($items);
        $quote = $first instanceof AbstractItem ? $first->getQuote() : null;
        $partner = $quote !== null ? $this->quotePartner->getPartner($quote) : null;
        if ($quote === null || $partner === null) {
            return false;
        }
        $country = strtoupper((string) $request->getDestCountryId());
        if (!($this->shippingConfig->getDestinations((int) $request->getStoreId())[$country]['home'] ?? false)) {
            return false;
        }
        $mode = $this->quotePartner->getMode($quote, $partner);
        $price = $this->orderConfig->getShippingPrice($mode);
        $freeFrom = $this->orderConfig->getStockFreeShippingFrom();
        if ($mode === PartnerPricing::MODE_STOCK && $freeFrom > 0 && (float) $request->getPackageValueWithDiscount() >= $freeFrom) {
            $price = 0.0;
        }

        $method = $this->rateMethodFactory->create();
        $method->setData('carrier', self::CODE);
        $method->setData('carrier_title', (string) $this->getConfigData('title'));
        $method->setData('method', self::CODE);
        $method->setData('method_title', (string) ($mode === PartnerPricing::MODE_STOCK ? __('Livraison à la boutique') : __('Expédition au client final')));
        $method->setPrice($price);
        $method->setData('cost', $price);

        $result = $this->rateResultFactory->create();
        $result->append($method);

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedMethods(): array
    {
        return [self::CODE => (string) $this->getConfigData('title')];
    }
}
