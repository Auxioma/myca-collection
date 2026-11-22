<?php

declare(strict_types=1);

namespace Myca\Shipping\Plugin;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Shipping\Model\Shipping;
use Myca\Shipping\Model\Carrier\Home;
use Myca\Shipping\Model\Config;
use Myca\Shipping\Model\FreeRelayThreshold;

/**
 * Règles MYCA appliquées aux tarifs des transporteurs :
 * - point relais retiré si le pays n'est pas desservi en relais, offert si le seuil est atteint ;
 * - livraison à domicile MYCA retirée si le pays n'est pas desservi à domicile.
 * Les transporteurs eux-mêmes restent les modules officiels (Mondial Relay, Colissimo…).
 */
class ApplyDestinationRules
{
    public function __construct(
        private readonly Config $config,
        private readonly FreeRelayThreshold $threshold
    ) {
    }

    public function afterCollectRates(Shipping $subject, Shipping $result, RateRequest $request): Shipping
    {
        $storeId = (int) $request->getStoreId();
        $relayCarriers = $this->config->getRelayCarrierCodes($storeId);
        $destination = $this->config->getDestinations($storeId)[strtoupper((string) $request->getDestCountryId())]
            ?? ['relay' => false, 'home' => false];

        $isFree = $this->threshold->isReached(
            $this->threshold->getEligibleAmount($request->getAllItems() ?: []),
            $storeId
        );

        $rateResult = $result->getResult();
        $kept = [];
        foreach ($rateResult->getAllRates() as $rate) {
            $carrier = (string) $rate->getData('carrier');
            $isRelay = in_array($carrier, $relayCarriers, true);

            if (($isRelay && !$destination['relay']) || ($carrier === Home::CODE && !$destination['home'])) {
                continue;
            }
            if ($isRelay && $isFree && $rate instanceof Method) {
                $rate->setPrice(0);
                $rate->setData('cost', 0);
            }
            $kept[] = $rate;
        }

        $rateResult->reset();
        foreach ($kept as $rate) {
            $rateResult->append($rate);
        }

        return $result;
    }
}
