<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);

namespace PayPal\Braintree\Model\AccountUpdater;

use PayPal\Braintree\Api\Data\NotificationInterface;
use Magento\Framework\DataObject;

class Notification extends DataObject implements NotificationInterface
{
    /**
     * @inheritDoc
     */
    public function getCardUpdatedAt(): ?string
    {
        return $this->getData(self::CARD_UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCardUpdatedAt(?string $cardUpdatedAt): NotificationInterface
    {
        return $this->setData(self::CARD_UPDATED_AT, $cardUpdatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodToken(): ?string
    {
        return $this->getData(self::PAYMENT_METHOD_TOKEN);
    }

    /**
     * @inheritDoc
     */
    public function setPaymentMethodToken(?string $paymentMethodToken): NotificationInterface
    {
        return $this->setData(self::PAYMENT_METHOD_TOKEN, $paymentMethodToken);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerId(): ?string
    {
        return $this->getData(self::CUSTOMER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId(?string $customerId): NotificationInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getOldLast4(): ?string
    {
        return $this->getData(self::OLD_LAST_4);
    }

    /**
     * @inheritDoc
     */
    public function setOldLast4(?string $oldLast4): NotificationInterface
    {
        return $this->setData(self::OLD_LAST_4, $oldLast4);
    }

    /**
     * @inheritDoc
     */
    public function getOldExpiration(): ?string
    {
        return $this->getData(self::OLD_EXPIRATION);
    }

    /**
     * @inheritDoc
     */
    public function setOldExpiration(?string $oldExpiration): NotificationInterface
    {
        return $this->setData(self::OLD_EXPIRATION, $oldExpiration);
    }

    /**
     * @inheritDoc
     */
    public function getNewLast4(): ?string
    {
        return $this->getData(self::NEW_LAST_4);
    }

    /**
     * @inheritDoc
     */
    public function setNewLast4(?string $newLast4): NotificationInterface
    {
        return $this->setData(self::NEW_LAST_4, $newLast4);
    }

    /**
     * @inheritDoc
     */
    public function getNewExpiration(): ?string
    {
        return $this->getData(self::NEW_EXPIRATION);
    }

    /**
     * @inheritDoc
     */
    public function setNewExpiration(?string $newExpiration): NotificationInterface
    {
        return $this->setData(self::NEW_EXPIRATION, $newExpiration);
    }

    /**
     * @inheritDoc
     */
    public function getNewCardType(): ?string
    {
        return $this->getData(self::NEW_CARD_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setNewCardType(?string $newCardType): NotificationInterface
    {
        return $this->setData(self::NEW_CARD_TYPE, $newCardType);
    }

    /**
     * @inheritDoc
     */
    public function getUpdateType(): ?string
    {
        return $this->getData(self::UPDATE_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setUpdateType(?string $updateType): NotificationInterface
    {
        return $this->setData(self::UPDATE_TYPE, $updateType);
    }

    /**
     * @inheritDoc
     */
    public function getSource(): ?string
    {
        return $this->getData(self::SOURCE);
    }

    /**
     * @inheritDoc
     */
    public function setSource(?string $source): NotificationInterface
    {
        return $this->setData(self::SOURCE, $source);
    }

    /**
     * @inheritDoc
     */
    public function getBin(): ?string
    {
        return $this->getData(self::BIN);
    }

    /**
     * @inheritDoc
     */
    public function setBin(?string $bin): NotificationInterface
    {
        return $this->setData(self::BIN, $bin);
    }
}
