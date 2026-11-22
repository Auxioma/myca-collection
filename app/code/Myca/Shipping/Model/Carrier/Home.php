<?php

declare(strict_types=1);

namespace Myca\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Myca\Shipping\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Livraison à domicile MYCA : tarif par pays et tranche de poids (barème modifiable dans la configuration).
 */
class Home extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'mycahome';

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
        private readonly Config $mycaConfig,
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

        $price = $this->findPrice(
            strtoupper((string) $request->getDestCountryId()),
            (float) $request->getPackageWeight(),
            (int) $request->getStoreId()
        );
        if ($price === null) {
            return false;
        }

        $method = $this->rateMethodFactory->create();
        $method->addData([
            'carrier' => self::CODE,
            'carrier_title' => (string) $this->getConfigData('title'),
            'method' => self::CODE,
            'method_title' => (string) $this->getConfigData('name'),
            'cost' => $price,
        ]);
        $method->setPrice($price);

        $result = $this->rateResultFactory->create();
        $result->append($method);

        return $result;
    }

    /**
     * Tranche [poids de, poids à[ ; un « poids à » nul signifie « sans limite ».
     */
    private function findPrice(string $country, float $weight, int $storeId): ?float
    {
        foreach ($this->mycaConfig->getHomeRates($storeId) as $rate) {
            $matchesCountry = $rate['country_id'] === $country || $rate['country_id'] === '*';
            $matchesWeight = $weight >= $rate['weight_from']
                && ($rate['weight_to'] <= 0 || $weight < $rate['weight_to']);
            if ($matchesCountry && $matchesWeight) {
                return $rate['price'];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedMethods(): array
    {
        return [self::CODE => (string) $this->getConfigData('name')];
    }
}
