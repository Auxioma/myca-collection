<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2021 Adobe
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

namespace Magento\PaymentServicesPaypal\Model\SmartButtons;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\SessionException;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\ApplePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\Config as PayPalConfig;
use Magento\PaymentServicesPaypal\Model\GooglePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote as QuoteEntity;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Checkout\Helper\Data;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Session\Generic as PaypalSession;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Checkout\Model\Type\Onepage;
use Magento\PaymentServicesPaypal\Model\SmartButtonsConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\PaymentServicesBase\Model\Config;
use Exception;
use Psr\Log\LoggerInterface;
use Random\RandomException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Checkout
{
    public const LOCATION_PRODUCT_PAGE = 'product';
    private const SUCCESS_PAGE_URI = 'checkout/onepage/success';
    private const SUCCESS_PAGE_PRODUCT_PAGE_CHECKOUT_URI = 'paymentservicespaypal/smartbuttons/success';
    public const SSSC_ALLOWED_PAYMENT_SOURCE = [
        'paypal',
        'paylater',
        'venmo'
    ];
    public const BXO_ALLOWED_PAYMENT_SOURCE = [
        'paypal',
        'paylater'
    ];
    public const NO_CONTACT_INFO = 'NO_CONTACT_INFO';
    public const UPDATE_CONTACT_INFO = 'UPDATE_CONTACT_INFO';
    public const RETAIN_CONTACT_INFO = 'RETAIN_CONTACT_INFO';

    /**
     * @var CartInterface|QuoteEntity|null
     */
    private $quote = null;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param CartManagementInterface $quoteManagement
     * @param Data $checkoutData
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param PaypalSession $paypalSession
     * @param OrderSender $orderSender
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     * @param Config $config
     * @param Data $checkoutHelper
     * @param OrderHelper $orderHelper
     * @param QuoteStateManager|null $quoteStateManager
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly CartManagementInterface $quoteManagement,
        private readonly Data $checkoutData,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly PaypalSession $paypalSession,
        private readonly OrderSender $orderSender,
        private readonly OrderService $orderService,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly Data $checkoutHelper,
        private readonly OrderHelper $orderHelper,
        private ?QuoteStateManager $quoteStateManager = null
    ) {
        $this->quoteStateManager = $quoteStateManager
            ?: ObjectManager::getInstance()->get(QuoteStateManager::class);
    }

    /**
     * Update quote function
     *
     * @param array $shippingAddress
     * @param array $billingAddress
     * @param string $orderId
     * @param string|null $payerId
     * @param string $paymentsOrderId
     * @param string $location
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function updateQuote(
        array $shippingAddress,
        array $billingAddress,
        string $orderId,
        ?string $payerId,
        string $paymentsOrderId,
        string $location
    ) : void {
        $this->getQuote()
            ->getShippingAddress()
            ->addData($shippingAddress)
            ->setCollectShippingRates(true);
        $this->getQuote()
            ->getBillingAddress()
            ->addData($billingAddress);
        $payment = $this->getQuote()->getPayment();
        if ($payerId !== null) {
            $payment->setAdditionalInformation('paypal_payer_id', $payerId);
        }
        $payment
            ->setAdditionalInformation('paypal_order_id', $orderId)
            ->setAdditionalInformation('payments_order_id', $paymentsOrderId)
            ->setAdditionalInformation('payments_mode', $this->config->getEnvironmentType())
            ->setAdditionalInformation('location', $this->orderHelper->validateCheckoutLocation($location));
        $this->quoteStateManager->discard($this->getQuote());
        $this->getQuote()->collectTotals();
        $this->quoteRepository->save($this->getQuote());
    }

    /**
     * Create an order in PayPal
     *
     * @param String $paymentSource
     * @param String|null $threeDSMode
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException|RandomException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function createPayPalOrder(string $paymentSource = '', ?string $threeDSMode = null) : array
    {
        $quote = $this->getQuote();

        if (!$quote->getId() || count($quote->getAllItems()) === 0) {
            throw new LocalizedException(
                __('Unable to create order: The cart is empty or unavailable. Please try again.')
            );
        }

        $location = $this->orderHelper->validateCheckoutLocation($this->getLocation());
        $isPayPalPaymentSource = in_array($paymentSource, self::SSSC_ALLOWED_PAYMENT_SOURCE);
        $isSsscExpress = $location !== PayPalConfig::CHECKOUT_CHECKOUT_LOCATION
            && $isPayPalPaymentSource;
        if ($isSsscExpress && $location !== PayPalConfig::PRODUCT_DETAIL_CHECKOUT_LOCATION) {
            $this->quoteStateManager->prepareForNewAttempt($quote);
        }

        $quote->reserveOrderId();
        $quote->getPayment()->setAdditionalInformation('payment_source', $paymentSource);
        $paymentMethod = SmartButtonsConfigProvider::CODE;
        if ($paymentSource === ApplePayConfigProvider::PAYMENT_SOURCE) {
            $paymentMethod = ApplePayConfigProvider::CODE;
        } elseif ($paymentSource === GooglePayConfigProvider::PAYMENT_SOURCE) {
            $paymentMethod = GooglePayConfigProvider::CODE;
        }
        $quote->getPayment()->setMethod($paymentMethod);
        $totalAmount = $quote->getBaseGrandTotal();
        $currencyCode = $quote->getCurrency()->getBaseCurrencyCode();

        $data = [
            'amount' => $this->orderHelper->formatAmount((float)$totalAmount),
            'currency_code' => $currencyCode,
            'is_digital' => $quote->getIsVirtual(),
            'payment_source' => $paymentSource,
            'three_ds_mode' => $threeDSMode ?: null,
            'quote_id' => $quote->getId(),
            'order_increment_id' => $this->orderHelper->reserveAndGetOrderIncrementId($quote),
            'line_items' => $this->orderHelper->getLineItems($quote, $quote->getReservedOrderId()),
            'amount_breakdown' => $this->orderHelper->getAmountBreakdown($quote, $quote->getReservedOrderId()),
            'location' => $location
        ];

        $data = $this->addPaymentSourceSpecificRequestData($quote, $data, $paymentSource, $location, $isSsscExpress);

        $saasResponse = $this->orderService->create($quote->getStore(), $data);

        if (isset($saasResponse['is_successful'])
            && $saasResponse['is_successful'] === true
            && isset($saasResponse["paypal-order"]['id'])
        ) {
            $quote->getPayment()->setAdditionalInformation('paypal_order_amount', $totalAmount);
            $quote->getPayment()->setAdditionalInformation('paypal_order_id', $saasResponse["paypal-order"]['id']);
            $quote->getPayment()->setAdditionalInformation(
                'payments_mode',
                $this->config->getEnvironmentType($quote->getStoreId())
            );
        }

        $this->quoteRepository->save($quote);

        return array_merge_recursive(
            $saasResponse,
            [
                "paypal-order" => [
                    "amount" => $totalAmount,
                    "currency_code" => $currencyCode
                ],
            ],
        );
    }

    /**
     * Add payment-source-specific data to a payment order request.
     *
     * Adds PayPal user action, SSSC shipping or address data, and BXO fields
     * according to the payment source and checkout location.
     *
     * @param CartInterface|Quote $quote
     * @param array $data
     * @param string $paymentSource
     * @param string $location
     * @param bool $isSsscExpress
     * @return array
     */
    private function addPaymentSourceSpecificRequestData(
        CartInterface|Quote $quote,
        array $data,
        string $paymentSource,
        string $location,
        bool $isSsscExpress
    ): array {
        $isPayPalPaymentSource = in_array($paymentSource, Checkout::SSSC_ALLOWED_PAYMENT_SOURCE, true);
        if ($isPayPalPaymentSource
            && ($location === PayPalConfig::CHECKOUT_CHECKOUT_LOCATION
                || $this->orderHelper->isPayPalSkipReviewEnabled((int)$quote->getStoreId()))
        ) {
            $data['user_action'] = $this->orderHelper->getUserAction();
        }

        // Server side shipping callback for PayPal, Pay Later and Venmo
        if ($isSsscExpress) {
            $shippingPreference = $this->orderHelper->getShippingPreference($quote);

            $data['shipping_preference'] = $shippingPreference;
            if ($shippingPreference === 'GET_FROM_FILE') {
                $data['order_update_callback_config'] = $this->orderHelper->getOrderUpdateCallbackConfig($quote);
            }
        }

        // BXO for PayPal
        if (in_array($paymentSource, self::BXO_ALLOWED_PAYMENT_SOURCE, true)) {
            // App Switch
            if ($this->orderHelper->isAppSwitchEnabled($quote->getStoreId())) {
                $data['return_url'] = $this->orderHelper->getCurrentPageUrl();
                $data['cancel_url'] = $this->orderHelper->getCurrentPageUrl();
            }

            // Contact Preference
            $data['contact_preference'] = self::NO_CONTACT_INFO;
            if ($this->orderHelper->isContactPreferenceEnabled($quote->getStoreId())) {
                $data['contact_preference'] = self::UPDATE_CONTACT_INFO;
            }
        }

        return $data;
    }

    /**
     * Set shipping method for quote
     *
     * @param string|null $methodCode
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function updateShippingMethod(string|null $methodCode) : void
    {
        $shippingAddress = $this->getQuote()->getShippingAddress();
        if (!$this->getQuote()->getIsVirtual() && $shippingAddress) {
            if ($methodCode != $shippingAddress->getShippingMethod()) {
                $this->ignoreAddressValidation();
                $shippingAddress->setShippingMethod($methodCode)
                    ->setCollectShippingRates(true);
                $cartExtensionAttributes = $this->getQuote()->getExtensionAttributes();
                if ($cartExtensionAttributes->getShippingAssignments()) {
                    $cartExtensionAttributes->getShippingAssignments()[0]
                        ->getShipping()
                        ->setMethod($methodCode);
                }
                $this->getQuote()->collectTotals();
                $this->quoteRepository->save($this->getQuote());
            }
        }
    }

    /**
     * Place an order
     *
     * @return OrderInterface|null
     * @throws LocalizedException
     * @throws SessionException
     */
    public function placeOrder() :? OrderInterface
    {
        if ($this->getCheckoutMethod() == Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote();
        }
        $this->ignoreAddressValidation();
        $this->getQuote()->collectTotals();
        $this->updatePayPalOrder();
        $this->updateQuoteCustomerData($this->getQuote());
        $this->quoteRepository->save($this->getQuote());
        $order = $this->quoteManagement->submit($this->getQuote());
        if (!$order) {
            return null;
        }
        try {
            if (!$order->getEmailSent()) {
                $this->orderSender->send($order);
            }
        } catch (Exception $e) {
            $this->logger->critical($e);
        }
        $this->checkoutSession->start();
        $this->checkoutSession->clearHelperData();
        $quoteId = $this->getQuote()->getId();
        $this->checkoutSession->setLastQuoteId($quoteId)
            ->setLastSuccessQuoteId($quoteId);
        if ($order) {
            $this->checkoutSession->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastOrderStatus($order->getStatus());
        }
        if ($this->paypalSession->getCustomerQuoteId()) {
            $quote = $this->quoteRepository->get($this->paypalSession->getCustomerQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(true);
                $this->quoteRepository->save($quote);
                $this->checkoutSession->setQuoteId($this->paypalSession->getCustomerQuoteId());
                $this->paypalSession->unsCustomerQuoteId();
                $this->paypalSession->unsQuoteId();
            }
        }
        return $order;
    }

    /**
     * Get quote method
     *
     * @return CartInterface|QuoteEntity
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote() : CartInterface|QuoteEntity
    {
        if (!$this->quote) {
            if ($this->paypalSession->getQuoteId()) {
                $this->quote = $this->quoteRepository->getActive($this->paypalSession->getQuoteId());
            } else {
                $this->quote = $this->checkoutSession->getQuote();
            }
        }
        return $this->quote;
    }

    /**
     * Unset quote method
     *
     * @return void
     */
    public function unsetQuote() : void
    {
        $this->paypalSession->unsCustomerQuoteId();
        $this->paypalSession->unsQuoteId();
    }

    /**
     * Validate quote method
     *
     * @throws LocalizedException
     */
    public function validateQuote() : void
    {
        try {
            $quote = $this->getQuote();
        } catch (LocalizedException | NoSuchEntityException $e) {
            throw new LocalizedException(__('Can\'t initialize checkout. Please try again.'));
        }
        if (!$quote->hasItems() || $quote->getHasError()) {
            throw new LocalizedException(__('Can\'t initialize checkout. Please try again.'));
        }
        if (!$this->customerSession->getCustomerId() &&
            !$this->checkoutHelper->isAllowedGuestCheckout($this->checkoutSession->getQuote())
        ) {
            throw new LocalizedException(__('To check out, please sign in with your email address.'));
        }
        if (!(float) $quote->getGrandTotal()) {
            throw new LocalizedException(
                __(
                    'Payment Services can\'t process orders with a zero balance due. '
                    . 'To finish your purchase, please go through the standard checkout process.'
                )
            );
        }
    }

    /**
     *  Set PayPal session location
     *
     * @param string $location
     */
    public function setLocation($location) : void
    {
        $this->paypalSession->setLocation($location);
    }

    /**
     * Get PayPal Session location
     *
     * @return string $location
     */
    public function getLocation() : string
    {
        return $this->paypalSession->getLocation() ?? '';
    }

    /**
     * Get successful page uri
     *
     * @return string
     */
    public function getSuccessPageUri() : string
    {
        if ($this->paypalSession->getLocation() === self::LOCATION_PRODUCT_PAGE) {
            return self::SUCCESS_PAGE_PRODUCT_PAGE_CHECKOUT_URI;
        }
        return self::SUCCESS_PAGE_URI;
    }

    /**
     * Get checkout method
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCheckoutMethod() : string
    {
        if ($this->customerSession->isLoggedIn()) {
            return Onepage::METHOD_CUSTOMER;
        }
        if (!$this->getQuote()->getCheckoutMethod()) {
            if ($this->checkoutData->isAllowedGuestCheckout($this->getQuote())) {
                $this->getQuote()->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $this->getQuote()->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
        return $this->getQuote()->getCheckoutMethod();
    }

    /**
     * Prepare quote for guest checkout
     */
    private function prepareGuestQuote() : void
    {
        $this->getQuote()
            ->setCustomerId(null)
            ->setCustomerEmail($this->getQuote()->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
    }

    /**
     * Disable addresses validation
     */
    private function ignoreAddressValidation() : void
    {
        $this->getQuote()
            ->getBillingAddress()
            ->setShouldIgnoreValidation(true);
        if (!$this->getQuote()->getIsVirtual()) {
            $this->getQuote()
                ->getShippingAddress()
                ->setShouldIgnoreValidation(true);
            if (!$this->getQuote()->getBillingAddress()->getEmail()) {
                $this->getQuote()
                    ->getBillingAddress()
                    ->setSameAsBilling(1);
            }
        }
    }

    /**
     * Update PayPal order with amount and currency info
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function updatePayPalOrder() : void
    {
        $quote = $this->getQuote();

        $orderIncrementId = $this->orderHelper->reserveAndGetOrderIncrementId($quote);
        $paypalOrderId = $quote->getPayment()->getAdditionalInformation('paypal_order_id');
        $totalAmount = $quote->getBaseGrandTotal();
        $currencyCode = $quote->getCurrency()->getBaseCurrencyCode();

        try {
            $this->orderService->update(
                (string) $quote->getStoreId(),
                (string) $paypalOrderId,
                [
                    'amount' => $this->orderHelper->formatAmount((float)$totalAmount),
                    'currency_code' => $currencyCode,
                    'line_items' => $this->orderHelper->getLineItems($quote, $orderIncrementId),
                    'amount_breakdown' => $this->orderHelper->getAmountBreakdown($quote, $orderIncrementId),
                ]
            );
        } catch (HttpException $e) {
            throw new LocalizedException(__('Your payment was not successful. Try again.'));
        }
    }

    /**
     * Update Quote with customer data from active session
     *
     * @param QuoteEntity $quote
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function updateQuoteCustomerData(QuoteEntity $quote): void
    {
        if ($this->customerSession->isLoggedIn()) {
            $customerData = $this->customerSession->getCustomerData();
            $quote->setCustomerFirstname($customerData->getFirstname());
            $quote->setCustomerLastname($customerData->getLastname());
        }
    }
}
