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

namespace PayPal\Braintree\Api\Data;

interface NotificationInterface
{
    public const CARD_UPDATED_AT = 'card_updated_at';
    public const PAYMENT_METHOD_TOKEN = 'payment_method_token';
    public const CUSTOMER_ID = 'customer_id';
    public const OLD_LAST_4 = 'old_last_4';
    public const OLD_EXPIRATION = 'old_expiration';
    public const NEW_LAST_4 = 'new_last_4';
    public const NEW_EXPIRATION = 'new_expiration';
    public const NEW_CARD_TYPE = 'new_card_type';
    public const UPDATE_TYPE = 'update_type';
    public const SOURCE = 'source';
    public const BIN = 'bin';

    /**
     * Get card updated at
     *
     * @return ?string
     */
    public function getCardUpdatedAt(): ?string;

    /**
     * Set card updated at
     *
     * @param ?string $cardUpdatedAt
     * @return self
     */
    public function setCardUpdatedAt(?string $cardUpdatedAt): self;

    /**
     * Get payment method token
     *
     * @return ?string
     */
    public function getPaymentMethodToken(): ?string;

    /**
     * Set payment method token
     *
     * @param ?string $paymentMethodToken
     * @return self
     */
    public function setPaymentMethodToken(?string $paymentMethodToken): self;

    /**
     * Get customer ID
     *
     * @return ?string
     */
    public function getCustomerId(): ?string;

    /**
     * Set customer ID
     *
     * @param ?string $customerId
     * @return self
     */
    public function setCustomerId(?string $customerId): self;

    /**
     * Get old last 4 digits
     *
     * @return ?string
     */
    public function getOldLast4(): ?string;

    /**
     * Set old last 5 digits
     *
     * @param ?string $oldLast4
     * @return self
     */
    public function setOldLast4(?string $oldLast4): self;

    /**
     * Get old expiration, Format: 23-Jun (year-month)
     *
     * @return ?string
     */
    public function getOldExpiration(): ?string;

    /**
     * Set old expiration, Format: 23-Jun (year-month)
     *
     * @param ?string $oldExpiration
     * @return self
     */
    public function setOldExpiration(?string $oldExpiration): self;

    /**
     * Get new last 4 digits
     *
     * @return ?string
     */
    public function getNewLast4(): ?string;

    /**
     * Set new last 4 digits
     *
     * @param ?string $newLast4
     * @return self
     */
    public function setNewLast4(?string $newLast4): self;

    /**
     * Get new expiration, Format: 23-Jun (year-month)
     *
     * @return ?string
     */
    public function getNewExpiration(): ?string;

    /**
     * Set new expiration, Format: 23-Jun (year-month)
     *
     * @param ?string $newExpiration
     * @return self
     */
    public function setNewExpiration(?string $newExpiration): self;

    /**
     * Get new card type
     *
     * @return ?string
     */
    public function getNewCardType(): ?string;

    /**
     * Set new card type
     *
     * @param ?string $newCardType
     * @return self
     */
    public function setNewCardType(?string $newCardType): self;

    /**
     * Get update type
     *
     * @return ?string
     */
    public function getUpdateType(): ?string;

    /**
     * Set update type
     *
     * @param ?string $updateType
     * @return self
     */
    public function setUpdateType(?string $updateType): self;

    /**
     * Get source
     *
     * @return ?string
     */
    public function getSource(): ?string;

    /**
     * Set source
     *
     * @param ?string $source
     * @return self
     */
    public function setSource(?string $source): self;

    /**
     * Get bin
     *
     * @return ?string
     */
    public function getBin(): ?string;

    /**
     * Set bin
     *
     * @param ?string $bin
     * @return self
     */
    public function setBin(?string $bin): self;
}
