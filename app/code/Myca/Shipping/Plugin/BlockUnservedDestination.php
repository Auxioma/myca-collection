<?php

declare(strict_types=1);

namespace Myca\Shipping\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Myca\Shipping\Model\Config;

/**
 * Refuse la commande si le pays de livraison n'est pas desservi (ni relais ni domicile).
 * Le blocage en cas d'indisponibilité de l'API transporteur viendra avec le choix des transporteurs (Q6).
 */
class BlockUnservedDestination
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $orderData
     * @return array{0: Quote, 1: array<string, mixed>}
     * @throws LocalizedException
     */
    public function beforeSubmit(QuoteManagement $subject, Quote $quote, array $orderData = []): array
    {
        if (!$quote->isVirtual()) {
            $country = strtoupper((string) $quote->getShippingAddress()->getCountryId());
            $destination = $this->config->getDestinations((int) $quote->getStoreId())[$country] ?? null;

            if (!$destination || (!$destination['relay'] && !$destination['home'])) {
                throw new LocalizedException(
                    __('Nous ne livrons pas encore vers ce pays. Merci de choisir une autre adresse de livraison.')
                );
            }
        }

        return [$quote, $orderData];
    }
}
