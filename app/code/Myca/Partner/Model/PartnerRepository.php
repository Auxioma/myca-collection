<?php

declare(strict_types=1);

namespace Myca\Partner\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Api\PartnerRepositoryInterface;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;

class PartnerRepository implements PartnerRepositoryInterface
{
    /** @var array<int, PartnerInterface> */
    private array $cache = [];

    public function __construct(
        private readonly PartnerFactory $partnerFactory,
        private readonly PartnerResource $resource
    ) {
    }

    public function getById(int $partnerId): PartnerInterface
    {
        if (!isset($this->cache[$partnerId])) {
            $partner = $this->partnerFactory->create();
            $this->resource->load($partner, $partnerId);
            if (!$partner->getId()) {
                throw new NoSuchEntityException(__('Partenaire %1 introuvable.', $partnerId));
            }
            $this->cache[$partnerId] = $partner;
        }

        return $this->cache[$partnerId];
    }

    public function getByCustomerId(int $customerId): PartnerInterface
    {
        $partnerId = $this->resource->getPartnerIdByCustomerId($customerId);
        if ($partnerId === null) {
            throw new NoSuchEntityException(__('Aucun partenaire pour ce compte.'));
        }

        return $this->getById($partnerId);
    }

    public function save(PartnerInterface $partner): PartnerInterface
    {
        try {
            /** @var Partner $partner */
            $this->resource->save($partner);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Le partenaire n’a pas pu être enregistré : %1', $e->getMessage()), $e);
        }
        unset($this->cache[(int) $partner->getId()]);

        return $partner;
    }
}
