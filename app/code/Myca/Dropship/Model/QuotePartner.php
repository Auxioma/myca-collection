<?php

declare(strict_types=1);

namespace Myca\Dropship\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Api\PartnerRepositoryInterface;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;
use Myca\Dropship\Model\ResourceModel\PartnerQuote;

/**
 * Boutique active et mode de vente d'un panier : seul le panier d'un compte rattaché à une boutique active est une
 * commande partenaire. Utilisé par les prix, le transporteur professionnel et le filtre des moyens de paiement.
 */
class QuotePartner
{
    /** @var array<int, PartnerInterface|null> */
    private array $partners = [];

    public function __construct(
        private readonly PartnerResource $partnerResource,
        private readonly PartnerRepositoryInterface $partnerRepository,
        private readonly PartnerQuote $partnerQuote
    ) {
    }

    public function getPartner(Quote $quote): ?PartnerInterface
    {
        $customerId = (int) $quote->getCustomerId();
        if ($customerId === 0) {
            return null;
        }
        if (!array_key_exists($customerId, $this->partners)) {
            $partnerId = $this->partnerResource->getPartnerIdByCustomerId($customerId);
            try {
                $partner = $partnerId !== null ? $this->partnerRepository->getById($partnerId) : null;
            } catch (NoSuchEntityException) {
                $partner = null;
            }
            $this->partners[$customerId] = $partner !== null && $partner->isActive() ? $partner : null;
        }

        return $this->partners[$customerId];
    }

    /**
     * Mode choisi pour ce panier ; à défaut, le premier mode autorisé à la boutique.
     */
    public function getMode(Quote $quote, PartnerInterface $partner): string
    {
        $allowed = $partner->getSalesModes() ?: [PartnerPricing::MODE_DROPSHIP];
        $stored = $quote->getId() ? $this->partnerQuote->get((int) $quote->getId()) : null;

        return $stored !== null && in_array($stored['mode'], $allowed, true) ? $stored['mode'] : (string) $allowed[0];
    }
}
