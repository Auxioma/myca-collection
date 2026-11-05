<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
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

namespace Magento\PaymentServicesPaypal\Model\ShippingCallback;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class QuoteStateManager
{
    public const SNAPSHOT_KEY = 'sssc_quote_state_snapshot';
    public const SESSION_ID_KEY = 'session_id';

    private const ADDRESS_FIELDS = [
        'firstname',
        'lastname',
        'telephone',
        'city',
        'country_id',
        'region',
        'region_id',
        'region_code',
        'postcode',
        'should_ignore_validation'
    ];

    private const ABANDONED_ORDER_FIELDS = [
        'paypal_order_id',
        'payments_order_id',
        'paypal_order_amount',
        'paypal_payer_id',
        self::SESSION_ID_KEY
    ];

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Capture quote values before the first SSSC callback changes them.
     *
     * The callback workflow persists this in-memory snapshot together with its
     * first address or shipping-method mutation.
     *
     * @param CartInterface|Quote $quote
     * @return void
     * @throws LocalizedException
     */
    public function capture(CartInterface|Quote $quote): void
    {
        $payment = $quote->getPayment();
        $paypalOrderId = (string) $payment->getAdditionalInformation('paypal_order_id');
        if ($paypalOrderId === '') {
            throw new LocalizedException(__('Unable to process shipping callback'));
        }

        $snapshot = $payment->getAdditionalInformation(self::SNAPSHOT_KEY);
        if (is_array($snapshot) && ($snapshot['paypal_order_id'] ?? null) === $paypalOrderId) {
            return;
        }

        $payment->setAdditionalInformation(self::SNAPSHOT_KEY, [
            'paypal_order_id' => $paypalOrderId,
            'shipping_address' => $this->getAddressData($quote->getShippingAddress()),
            'billing_address' => $this->getAddressData($quote->getBillingAddress()),
            'shipping_method' => $quote->getShippingAddress()->getShippingMethod(),
            'shipping_description' => $quote->getShippingAddress()->getShippingDescription(),
            'shipping_limit_carrier' => $quote->getShippingAddress()->getLimitCarrier()
        ]);
    }

    /**
     * Check whether the quote contains recoverable state for an SSSC attempt.
     *
     * @param CartInterface|Quote $quote
     * @return bool
     */
    public function hasActiveSsscAttempt(CartInterface|Quote $quote): bool
    {
        $payment = $quote->getPayment();
        return !empty($payment->getAdditionalInformation(self::SESSION_ID_KEY))
            || is_array($payment->getAdditionalInformation(self::SNAPSHOT_KEY));
    }

    /**
     * Restore quote values after an aborted SSSC attempt.
     *
     * Snapshot ownership is determined only by the PayPal order ID. The
     * callback session ID is not stored in or compared with the snapshot.
     *
     * @param CartInterface|Quote $quote
     * @return bool
     */
    public function restore(CartInterface|Quote $quote): bool
    {
        $payment = $quote->getPayment();
        $snapshot = $payment->getAdditionalInformation(self::SNAPSHOT_KEY);
        $hasSnapshot = is_array($snapshot);
        $hasActiveCallbackSession = !empty($payment->getAdditionalInformation(self::SESSION_ID_KEY));
        if (!$hasSnapshot && !$hasActiveCallbackSession) {
            return false;
        }

        $paypalOrderId = (string) $payment->getAdditionalInformation('paypal_order_id');
        if ($hasSnapshot && ($snapshot['paypal_order_id'] ?? null) !== $paypalOrderId) {
            $payment->unsAdditionalInformation(self::SNAPSHOT_KEY);
            $this->quoteRepository->save($quote);

            return false;
        }

        if ($hasSnapshot) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->addData($snapshot['shipping_address'] ?? []);
            $shippingAddress->setShippingMethod($snapshot['shipping_method'] ?? null);
            $shippingAddress->setShippingDescription($snapshot['shipping_description'] ?? null);
            $shippingAddress->setLimitCarrier($snapshot['shipping_limit_carrier'] ?? null);
            $shippingAddress->removeAllShippingRates();
            $shippingAddress->setCollectShippingRates(true);

            $quote->getBillingAddress()->addData($snapshot['billing_address'] ?? []);
            $this->restoreShippingAssignments($quote, $shippingAddress, $snapshot['shipping_method'] ?? null);
        }

        $payment->unsAdditionalInformation(self::SNAPSHOT_KEY);
        foreach (self::ABANDONED_ORDER_FIELDS as $field) {
            $payment->unsAdditionalInformation($field);
        }

        if ($hasSnapshot) {
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
        }
        $this->quoteRepository->save($quote);

        return $hasSnapshot;
    }

    /**
     * Restore or clean up an abandoned attempt before creating another one.
     *
     * @param CartInterface|Quote $quote
     * @return void
     */
    public function prepareForNewAttempt(CartInterface|Quote $quote): void
    {
        if ($this->hasActiveSsscAttempt($quote)) {
            $this->restore($quote);
        }
    }

    /**
     * Discard recovery state after successful PayPal synchronization.
     *
     * @param CartInterface|Quote $quote
     * @return void
     */
    public function discard(CartInterface|Quote $quote): void
    {
        $payment = $quote->getPayment();
        $payment->unsAdditionalInformation(self::SNAPSHOT_KEY);
        $payment->unsAdditionalInformation(self::SESSION_ID_KEY);
    }

    /**
     * Restore an abandoned attempt without hiding restoration failures.
     *
     * @param CartInterface|Quote $quote
     * @return void
     * @throws LocalizedException
     */
    public function recover(CartInterface|Quote $quote): void
    {
        if (!$this->hasActiveSsscAttempt($quote)) {
            return;
        }

        try {
            $this->restore($quote);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to recover quote after a failed PayPal operation.', [
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode()
            ]);
            $cause = $exception instanceof Exception
                ? $exception
                : new RuntimeException(
                    'Unable to recover quote after a failed PayPal operation.',
                    (int) $exception->getCode(),
                    $exception
                );
            throw new LocalizedException(__('Unable to restore the cart. Please try again.'), $cause);
        }
    }

    /**
     * Get address values changed by SSSC callbacks.
     *
     * @param Address $address
     * @return array
     */
    private function getAddressData(Address $address): array
    {
        $data = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $data[$field] = $address->getData($field);
        }

        return $data;
    }

    /**
     * Restore the shipping method kept in the quote extension attributes.
     *
     * @param CartInterface|Quote $quote
     * @param Address $shippingAddress
     * @param string|null $shippingMethod
     * @return void
     */
    private function restoreShippingAssignments(
        CartInterface|Quote $quote,
        Address $shippingAddress,
        ?string $shippingMethod
    ): void {
        $shippingAssignments = $quote->getExtensionAttributes()?->getShippingAssignments() ?? [];
        foreach ($shippingAssignments as $shippingAssignment) {
            $shipping = $shippingAssignment->getShipping();
            if ($shipping !== null) {
                $shipping->setAddress($shippingAddress);
                $shipping->setMethod($shippingMethod);
            }
        }
    }
}
