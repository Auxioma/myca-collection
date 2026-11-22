<?php

declare(strict_types=1);

namespace Myca\Shipping\Plugin;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Myca\Shipping\Model\Config;
use Myca\Shipping\Model\FreeRelayThreshold;

/**
 * Ajoute la progression « point relais offert » aux données du mini-panier (section « cart »),
 * pour le message « Plus que X € » de la fenêtre d'ajout au panier.
 */
class AddFreeRelayToCartSection
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly FreeRelayThreshold $threshold,
        private readonly Config $config,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $quote = $this->checkoutSession->getQuote();
        $storeId = (int) $quote->getStoreId();
        $limit = $this->config->getFreeRelayThreshold($storeId);
        if ($limit <= 0) {
            return $result;
        }

        $eligible = $this->threshold->getEligibleAmount($quote->getAllItems());
        $result['myca_free_relay'] = [
            'reached' => $this->threshold->isReached($eligible, $storeId),
            'remaining' => $this->priceCurrency->format($this->threshold->getRemaining($eligible, $storeId), false),
            'threshold' => $this->priceCurrency->format($limit, false),
        ];

        return $result;
    }
}
