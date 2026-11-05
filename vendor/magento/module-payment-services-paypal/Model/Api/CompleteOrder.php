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

namespace Magento\PaymentServicesPaypal\Model\Api;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesPaypal\Api\CompleteOrderInterface;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\AddressConverter;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\QuoteSynchronizer;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class CompleteOrder implements CompleteOrderInterface
{
    /**
     * @param OrderService $orderService
     * @param Checkout $checkout
     * @param AddressConverter $addressConverter
     * @param UrlInterface $urlBuilder
     * @param CartManagementInterface $cartManagement
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param LoggerInterface $logger
     * @param QuoteSynchronizer|null $quoteSynchronizer
     * @param QuoteStateManager|null $quoteStateManager
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly OrderService $orderService,
        private readonly Checkout $checkout,
        private readonly AddressConverter $addressConverter,
        private readonly UrlInterface $urlBuilder,
        private readonly CartManagementInterface $cartManagement,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly LoggerInterface $logger,
        private ?QuoteSynchronizer $quoteSynchronizer = null,
        private ?QuoteStateManager $quoteStateManager = null
    ) {
        $this->quoteSynchronizer = $quoteSynchronizer ?:
            ObjectManager::getInstance()->get(QuoteSynchronizer::class);
        $this->quoteStateManager = $quoteStateManager ?:
            ObjectManager::getInstance()->get(QuoteStateManager::class);
    }

    /**
     * Rest API endpoint to place an order
     *
     * @param string $orderId @deprecated The PayPal order ID is now read from payment additional information
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(string $orderId): string
    {
        $location = null;
        $quote = null;
        try {
            $location = $this->checkout->getLocation();
            $quote = $this->checkout->getQuote();
            $storeId = $quote->getStoreId();
            $paypalOrderId = $quote->getPayment()?->getAdditionalInformation('paypal_order_id');
            if (empty($paypalOrderId)) {
                throw new LocalizedException(__('PayPal order not found. Please try again.'));
            }
            $order = $this->orderService->get((string)$storeId, $paypalOrderId);
            $payerId = $order['paypal-order']['payer']['payer_id'] ?? null;
            $this->checkout->updateQuote(
                $this->addressConverter->convertShippingAddress($order),
                $this->addressConverter->convertBillingAddress($order),
                $paypalOrderId,
                $payerId,
                $order['paypal-order']['mp_order_id'],
                $location
            );

            $this->placeOrderAndClearQuote($quote);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            if ($quote !== null && $location !== $this->checkout::LOCATION_PRODUCT_PAGE) {
                try {
                    $this->quoteStateManager->recover($quote);
                } catch (Throwable) {
                    // QuoteStateManager logs recovery failures using sanitized context.
                    return json_encode([
                        'success' => false
                    ]);
                }
            }

            return json_encode([
                'success' => false
            ]);
        }

        return json_encode([
            'success' => true,
            'redirectUrl' => $this->urlBuilder->getUrl('checkout/onepage/success/')
        ]);
    }

    /**
     * GraphQL endpoint to update the quote and place an order
     *
     * @param int $cartId
     * @param string $orderId @deprecated The PayPal order ID is now read from payment additional information
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function syncAndPlaceOrder(int $cartId, string $orderId): int
    {
        $quote = $this->quoteRepository->getActive($cartId);

        if (!$quote->getId() || count($quote->getAllItems()) === 0) {
            throw new LocalizedException(
                __('Unable to create order: The cart is empty or unavailable. Please try again.')
            );
        }

        $this->quoteSynchronizer->synchronize($quote);

        $quote->collectTotals();
        $this->quoteRepository->save($quote);

        return $this->placeOrderAndClearQuote($quote);
    }

    /**
     * Place an order and clear the quote
     *
     * @param CartInterface|Quote $quote
     * @return int
     * @throws Exception
     */
    private function placeOrderAndClearQuote(CartInterface|Quote $quote): int
    {
        $orderId = $this->cartManagement->placeOrder($quote->getId());

        // Invalidate cart
        $quote->setIsActive(false)->save();

        // Clear checkout session to avoid using the old quote
        $this->checkoutSession->clearQuote();

        // Store order id for success page
        $this->checkoutSession
            ->setLastSuccessQuoteId($quote->getId())
            ->setLastQuoteId($quote->getId())
            ->setLastOrderId($orderId);

        return (int) $orderId;
    }
}
