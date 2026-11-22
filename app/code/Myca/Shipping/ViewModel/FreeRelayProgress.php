<?php

declare(strict_types=1);

namespace Myca\Shipping\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Shipping\Model\Config;
use Myca\Shipping\Model\FreeRelayThreshold;

/**
 * Progression vers le point relais offert, affichée sur le panier (P06) : montant restant et pourcentage.
 */
class FreeRelayProgress implements ArgumentInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly FreeRelayThreshold $threshold,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{threshold: float, eligible: float, remaining: float, reached: bool, percent: int}
     */
    public function get(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $storeId = (int) $quote->getStoreId();
        $eligible = $this->threshold->getEligibleAmount($quote->getAllItems());
        $limit = $this->config->getFreeRelayThreshold($storeId);

        return [
            'threshold' => $limit,
            'eligible' => $eligible,
            'remaining' => $this->threshold->getRemaining($eligible, $storeId),
            'reached' => $this->threshold->isReached($eligible, $storeId),
            'percent' => $limit > 0 ? (int) min(100, floor($eligible / $limit * 100)) : 0,
        ];
    }
}
