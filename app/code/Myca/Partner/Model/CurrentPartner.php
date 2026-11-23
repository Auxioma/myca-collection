<?php

declare(strict_types=1);

namespace Myca\Partner\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Api\PartnerRepositoryInterface;

/**
 * Partenaire du client connecté. Seul un partenaire actif ouvre l'espace partenaire.
 */
class CurrentPartner
{
    private ?PartnerInterface $partner = null;
    private bool $loaded = false;

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly PartnerRepositoryInterface $partnerRepository
    ) {
    }

    public function get(): ?PartnerInterface
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $customerId = (int) $this->customerSession->getCustomerId();
            if ($customerId > 0) {
                try {
                    $this->partner = $this->partnerRepository->getByCustomerId($customerId);
                } catch (NoSuchEntityException) {
                    $this->partner = null;
                }
            }
        }

        return $this->partner;
    }

    /**
     * Partenaire actif du client connecté, ou null (non connecté, pas partenaire, en attente, suspendu…).
     */
    public function getActive(): ?PartnerInterface
    {
        $partner = $this->get();

        return $partner !== null && $partner->isActive() ? $partner : null;
    }

    public function getActiveId(): ?int
    {
        $partner = $this->getActive();

        return $partner === null ? null : (int) $partner->getId();
    }
}
