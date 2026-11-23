<?php

declare(strict_types=1);

namespace Myca\Rma\Model;

use Magento\Framework\Model\AbstractModel;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;

/**
 * Demande de retour, d'échange ou de SAV (table myca_rma). Elle ne se modifie que par RmaManagement.
 */
class Rma extends AbstractModel
{
    protected $_eventPrefix = 'myca_rma';

    protected function _construct(): void
    {
        $this->_init(RmaResource::class);
    }

    public function getIncrementId(): string
    {
        return (string) $this->getData('increment_id');
    }

    public function getOrderId(): int
    {
        return (int) $this->getData('order_id');
    }

    public function getOrigin(): string
    {
        return (string) $this->getData('origin');
    }

    public function getType(): string
    {
        return (string) $this->getData('type');
    }

    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    public function getPartnerId(): ?int
    {
        $partnerId = $this->getData('partner_id');

        return $partnerId === null ? null : (int) $partnerId;
    }

    public function getCustomerId(): ?int
    {
        $customerId = $this->getData('customer_id');

        return $customerId === null ? null : (int) $customerId;
    }

    public function getCreditmemoId(): ?int
    {
        $creditmemoId = $this->getData('creditmemo_id');

        return $creditmemoId === null ? null : (int) $creditmemoId;
    }
}
