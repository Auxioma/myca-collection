<?php

declare(strict_types=1);

namespace Myca\Partner\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Myca\Partner\Api\Data\PartnerInterface;

interface PartnerRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $partnerId): PartnerInterface;

    /**
     * Partenaire auquel appartient un compte client (quel que soit son statut).
     *
     * @throws NoSuchEntityException
     */
    public function getByCustomerId(int $customerId): PartnerInterface;

    /**
     * @throws CouldNotSaveException
     */
    public function save(PartnerInterface $partner): PartnerInterface;
}
