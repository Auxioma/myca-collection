<?php

declare(strict_types=1);

namespace Myca\Dropship\Plugin;

use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Myca\Dropship\Model\OrderConfig;
use Myca\Dropship\Model\QuotePartner;

/**
 * Moyens de paiement selon l'acheteur : une boutique ne voit que les moyens prévus pour les partenaires ; un
 * particulier ne voit jamais les moyens réservés aux partenaires (virement à échéance, par exemple).
 */
class FilterPaymentMethods
{
    public function __construct(
        private readonly QuotePartner $quotePartner,
        private readonly OrderConfig $config
    ) {
    }

    /**
     * @param MethodInterface[] $result
     * @return MethodInterface[]
     */
    public function afterGetAvailableMethods(MethodList $subject, array $result, ?CartInterface $quote = null): array
    {
        $isPartner = $quote instanceof Quote && $this->quotePartner->getPartner($quote) !== null;
        $allowed = $this->config->getPartnerPaymentMethods();
        $partnerOnly = $this->config->getPartnerOnlyPaymentMethods();

        return array_values(array_filter($result, static function (MethodInterface $method) use ($isPartner, $allowed, $partnerOnly): bool {
            $code = $method->getCode();

            return $isPartner ? in_array($code, $allowed, true) : !in_array($code, $partnerOnly, true);
        }));
    }
}
