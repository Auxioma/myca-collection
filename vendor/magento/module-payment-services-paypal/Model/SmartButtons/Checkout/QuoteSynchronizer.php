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

namespace Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote as QuoteEntity;
use RuntimeException;
use Throwable;

class QuoteSynchronizer
{
    /**
     * @param OrderService $orderService
     * @param AddressConverter $addressConverter
     * @param QuoteStateManager|null $quoteStateManager
     */
    public function __construct(
        private readonly OrderService $orderService,
        private readonly AddressConverter $addressConverter,
        private ?QuoteStateManager $quoteStateManager = null
    ) {
        $this->quoteStateManager = $quoteStateManager
            ?: ObjectManager::getInstance()->get(QuoteStateManager::class);
    }

    /**
     * Update a quote with the payer and address data from PayPal.
     *
     * @param CartInterface|QuoteEntity $quote
     * @return void
     * @throws Exception
     */
    public function synchronize(CartInterface|QuoteEntity $quote): void
    {
        try {
            $paypalOrderId = $quote->getPayment()?->getAdditionalInformation('paypal_order_id');
            if (empty($paypalOrderId)) {
                throw new LocalizedException(__('PayPal order not found. Please try again.'));
            }

            $order = $this->orderService->get((string) $quote->getStoreId(), (string) $paypalOrderId);

            $shippingAddress = $this->addressConverter->convertShippingAddress($order);
            $quote->getShippingAddress()->addData($shippingAddress)->setCollectShippingRates(true);
            $billingAddress = $this->addressConverter->convertBillingAddress($order);
            $quote->getBillingAddress()->addData($billingAddress);

            if (!$quote->getCustomerEmail() && isset($order['paypal-order']['payer']['email'])) {
                $quote->setCustomerEmail($order['paypal-order']['payer']['email']);
            }

            if (isset($order['paypal-order']['payer']['payer_id'])) {
                $quote->getPayment()->setAdditionalInformation(
                    'paypal_payer_id',
                    $order['paypal-order']['payer']['payer_id']
                );
            }

            $this->quoteStateManager->discard($quote);
        } catch (Throwable $exception) {
            $this->quoteStateManager->recover($quote);
            if ($exception instanceof Exception) {
                throw $exception;
            }

            throw new RuntimeException(
                'Unable to synchronize PayPal order.',
                (int) $exception->getCode(),
                $exception
            );
        }
    }
}
