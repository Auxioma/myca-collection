<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2023 Adobe
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

namespace Magento\PaymentServicesPaypal\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesPaypal\Api\Data\PaymentCardAuthenticationResultDetailsInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderInterface;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\AddressConverter;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\PaymentServicesPaypal\Api\PaymentOrderManagementInterface;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderInterfaceFactory;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderDetailsInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderDetailsInterfaceFactory;
use Magento\PaymentServicesPaypal\Api\Data\PaymentSourceDetailsInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentSourceDetailsInterfaceFactory;
use Magento\PaymentServicesPaypal\Api\Data\PaymentCardDetailsInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentCardDetailsInterfaceFactory;
use Magento\PaymentServicesPaypal\Api\Data\PaymentCardDetailsWithAuthenticationResultInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentCardBinDetailsInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentCardBinDetailsInterfaceFactory;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\PaymentServicesPaypal\Model\Api\Data\PaymentCardAuthenticationResultDetailsFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentOrderManagement implements PaymentOrderManagementInterface
{
    private const NO = 'NO';
    private const POSSIBLE = 'POSSIBLE';
    private const UNKNOWN = 'UNKNOWN';
    private const LIABILITY_SHIFT = [
        self::NO,
        self::POSSIBLE,
        self::UNKNOWN
    ];

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderService $orderService
     * @param PaymentOrderInterfaceFactory $paymentOrderFactory
     * @param PaymentOrderDetailsInterfaceFactory $paymentOrderDetailsFactory
     * @param PaymentSourceDetailsInterfaceFactory $paymentSourceDetailsFactory
     * @param PaymentCardDetailsInterfaceFactory $paymentCardDetailsFactory
     * @param PaymentCardBinDetailsInterfaceFactory $paymentCardBinDetailsFactory
     * @param array $validMethodCodes
     * @param OrderHelper $orderHelper
     * @param AddressConverter $addressConverter
     * @param BaseConfig $config
     * @param PaymentCardAuthenticationResultDetailsFactory|null $paymentCardAuthResultDetailsFactory
     * @param QuoteStateManager|null $quoteStateManager
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly OrderService $orderService,
        private readonly PaymentOrderInterfaceFactory $paymentOrderFactory,
        private readonly PaymentOrderDetailsInterfaceFactory $paymentOrderDetailsFactory,
        private readonly PaymentSourceDetailsInterfaceFactory $paymentSourceDetailsFactory,
        private readonly PaymentCardDetailsInterfaceFactory $paymentCardDetailsFactory,
        private readonly PaymentCardBinDetailsInterfaceFactory $paymentCardBinDetailsFactory,
        private readonly array $validMethodCodes,
        private readonly OrderHelper $orderHelper,
        private readonly AddressConverter $addressConverter,
        private readonly BaseConfig $config,
        private ?PaymentCardAuthenticationResultDetailsFactory $paymentCardAuthResultDetailsFactory = null,
        private ?QuoteStateManager $quoteStateManager = null
    ) {
        $this->paymentCardAuthResultDetailsFactory = $paymentCardAuthResultDetailsFactory
            ?: ObjectManager::getInstance()->get(PaymentCardAuthenticationResultDetailsFactory::class);
        $this->quoteStateManager = $quoteStateManager
            ?: ObjectManager::getInstance()->get(QuoteStateManager::class);
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function create(
        string $methodCode,
        string $paymentSource,
        int    $cartId,
        string $location,
        bool   $vaultIntent = false,
        ?int    $customerId = null,
        ?string $threeDSMode = null
    ) : PaymentOrderInterface {
        if (!in_array($methodCode, $this->validMethodCodes)) {
            throw new InvalidArgumentException(__('Invalid methodCode'));
        }

        $quote = $this->quoteRepository->getActive($cartId);

        if (!$quote->getId() || count($quote->getAllItems()) === 0) {
            throw new LocalizedException(__(
                'Unable to create order: The cart is empty or unavailable. Please try again.'
            ));
        }

        $location = $this->orderHelper->validateCheckoutLocation($location);
        $isPayPalPaymentSource = in_array($paymentSource, Checkout::SSSC_ALLOWED_PAYMENT_SOURCE);
        $isSsscExpress = $location !== Config::CHECKOUT_CHECKOUT_LOCATION
            && $isPayPalPaymentSource;
        $shouldRecoverSsscQuote = $isSsscExpress && $location !== Config::PRODUCT_DETAIL_CHECKOUT_LOCATION;
        if ($shouldRecoverSsscQuote) {
            $this->quoteStateManager->prepareForNewAttempt($quote);
        }

        $isLoggedIn = (bool)$customerId;
        $paymentMethod = $quote->getPayment();
        $paymentMethod->setAdditionalInformation('payment_source', $paymentSource);
        if (!empty($location)) {
            $paymentMethod->setAdditionalInformation('location', $location);
        }

        $paymentMethod->setMethod($methodCode);
        $orderIncrementId = $this->orderHelper->reserveAndGetOrderIncrementId($quote);

        $data = [
            'amount' => $this->orderHelper->formatAmount((float)$quote->getBaseGrandTotal()),
            /** @phpstan-ignore-next-line */
            'l2_data' => $this->orderHelper->getL2Data($quote, $paymentSource ?? ''),
            /** @phpstan-ignore-next-line */
            'l3_data' => $this->orderHelper->getL3Data($quote, $paymentSource ?? ''),
            'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
            'is_digital' => $quote->isVirtual(),
            // TODO: Setting 'storeview_code' to storeview id (not code) seems incorrect.
            'storeview_code' => $quote->getStoreId(),
            'payment_source' => $paymentSource,
            'quote_id' => $quote->getId(),
            'payer' => $isLoggedIn
                ? $this->orderService->buildPayer($quote, (string)$customerId)
                : $this->orderService->buildGuestPayer($quote),
            'vault' => $vaultIntent,
            'order_increment_id' => $orderIncrementId,
            'line_items' => $this->orderHelper->getLineItems($quote, $orderIncrementId),
            'amount_breakdown' => $this->orderHelper->getAmountBreakdown($quote, $orderIncrementId),
            'three_ds_mode' => $threeDSMode,
            'location' => $location
        ];

        $data = $this->addPaymentSourceSpecificRequestData($quote, $data, $paymentSource, $location, $isSsscExpress);

        try {
            $orderServiceResponse = $this->orderService->create($quote->getStore(), $data);
        } catch (\Throwable $exception) {
            if ($shouldRecoverSsscQuote) {
                $this->quoteStateManager->recover($quote);
            }
            throw $exception;
        }

        if (isset($orderServiceResponse['paypal-order'])) {
            $paypalOrder = $orderServiceResponse['paypal-order'];
            $response = $this->paymentOrderFactory->create();
            $response->setId($paypalOrder['id'])
                ->setMpOrderId($paypalOrder['mp_order_id'])
                ->setStatus($paypalOrder['status'])
                ->setAmount((float)$this->orderHelper->formatAmount((float)$quote->getBaseGrandTotal()))
                ->setCurrencyCode($quote->getCurrency()->getBaseCurrencyCode());
            $paymentMethod->setAdditionalInformation('paypal_order_id', $paypalOrder['id']);
            $paymentMethod->setAdditionalInformation('payments_order_id', $paypalOrder['mp_order_id']);
            $paymentMethod->setAdditionalInformation('paypal_order_amount', $quote->getBaseGrandTotal());
            $paymentMethod->setAdditionalInformation(
                'payments_mode',
                $this->config->getEnvironmentType($quote->getStoreId())
            );
            $this->quoteRepository->save($quote);
            return $response;
        } else {
            if ($shouldRecoverSsscQuote && $this->quoteStateManager->hasActiveSsscAttempt($quote)) {
                $this->quoteStateManager->recover($quote);
            } else {
                $this->quoteRepository->save($quote);
            }
            $message = 'Failed to create an order';
            if (isset($orderServiceResponse['message'])) {
                $message = $message . ": " . $orderServiceResponse['message'];
            }
            throw new LocalizedException(__($message));
        }
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
        $isPayPalPaymentSource = in_array($paymentSource, Checkout::SSSC_ALLOWED_PAYMENT_SOURCE);
        if ($isPayPalPaymentSource
            && ($location === Config::CHECKOUT_CHECKOUT_LOCATION
                || $this->orderHelper->isPayPalSkipReviewEnabled((int)$quote->getStoreId()))
        ) {
            $data['user_action'] = $this->orderHelper->getUserAction();
        }

        // For SSSC express orders, let PayPal provide the shipping address through the callback.
        // Do not send callback-derived quote addresses when creating the order.
        if ($isSsscExpress) {
            $shippingPreference = $this->orderHelper->getShippingPreference($quote);

            $data['shipping_preference'] = $shippingPreference;
            if ($shippingPreference === 'GET_FROM_FILE') {
                $data['order_update_callback_config'] = $this->orderHelper->getOrderUpdateCallbackConfig($quote);
            }
        } else {
            $data['shipping_address'] = $this->orderService->mapAddress($quote->getShippingAddress());
            $data['billing_address'] = $this->orderService->mapAddress($quote->getBillingAddress());
        }

        // BXO for PayPal
        return $this->addBxoRequestDataForPayPal($paymentSource, (int)$quote->getStoreId(), $data);
    }

    /**
     * Add BXO (App Switch and Contact Module) request data
     *
     * @param string|null $paymentSource
     * @param int $storeId
     * @param array $data
     * @return array
     * @throws NoSuchEntityException
     */
    private function addBxoRequestDataForPayPal(
        ?string $paymentSource,
        int $storeId,
        array $data
    ): array {
        if (in_array($paymentSource, Checkout::BXO_ALLOWED_PAYMENT_SOURCE)) {
            // App Switch
            if ($this->orderHelper->isAppSwitchEnabled($storeId)) {
                $data['return_url'] = $this->orderHelper->getCurrentPageUrl();
                $data['cancel_url'] = $this->orderHelper->getCurrentPageUrl();
            }

            // Contact Preference
            $data['contact_preference'] = Checkout::NO_CONTACT_INFO;
            if ($this->orderHelper->isContactPreferenceEnabled($storeId)) {
                if ($data['location'] === Config::CHECKOUT_CHECKOUT_LOCATION) {
                    $data['contact_preference'] = Checkout::RETAIN_CONTACT_INFO;
                } else {
                    $data['contact_preference'] = Checkout::UPDATE_CONTACT_INFO;
                }
            }
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function get(
        int $cartId,
        string $id,
        ?int $customerId = null
    ) {
        $quote = $this->quoteRepository->getActive($cartId);
        $orderId = $quote->getPayment()->getAdditionalInformation('paypal_order_id');

        // Check if passed in order ID is valid
        $message = 'Failed to get payment order';
        if ($id !== $orderId) {
            $message = $message . ": " . "order ID input is not valid" ;
            throw new LocalizedException(__($message));
        }

        $orderServiceResponse = $this->orderService->get((string) $quote->getStoreId(), $orderId);
        if (isset($orderServiceResponse['paypal-order'])) {
            $paypalOrder = $orderServiceResponse['paypal-order'];
            /** @var PaymentOrderDetailsInterface $response */
            $response = $this->paymentOrderDetailsFactory->create();
            $response->setId($paypalOrder['id'])
                ->setMpOrderId($paypalOrder['mp_order_id'])
                ->setStatus($paypalOrder['status']);
            if (isset($paypalOrder['payment_source_details'])) {
                $response->setPaymentSourceDetails(
                    $this->getPaymentSourceDetails($paypalOrder['payment_source_details'])
                );
            }
            return $response;
        } else {
            if (isset($orderServiceResponse['message'])) {
                $message = $message . ": " . $orderServiceResponse['message'];
            }
            throw new LocalizedException(__($message));
        }
    }

    /**
     * @inheritdoc
     */
    public function sync(
        int $cartId,
        string $id,
        ?int $customerId = null
    ) {
        $quote = $this->quoteRepository->getActive($cartId);
        $orderId = $quote->getPayment()->getAdditionalInformation('paypal_order_id');

        // Check if passed in order ID is valid
        $message = 'Failed to get payment order';
        if ($id !== $orderId) {
            $message = $message . ": " . "order ID input is not valid" ;
            throw new LocalizedException(__($message));
        }

        try {
            $orderServiceResponse = $this->orderService->get((string) $quote->getStoreId(), $orderId);

            if (isset($orderServiceResponse['paypal-order'])) {
                $shippingAddress = $this->addressConverter->convertShippingAddress($orderServiceResponse);
                $quote->getShippingAddress()->addData($shippingAddress)->setCollectShippingRates(true);
                $billingAddress = $this->addressConverter->convertBillingAddress($orderServiceResponse);
                $quote->getBillingAddress()->addData($billingAddress);
                $quote->setCustomerEmail($orderServiceResponse['paypal-order']['payer']['email']);
                $quote->getPayment()->setAdditionalInformation(
                    'paypal_payer_id',
                    $orderServiceResponse['paypal-order']['payer']['payer_id']
                );
                $this->quoteStateManager->discard($quote);
                $quote->collectTotals();
                $this->quoteRepository->save($quote);
            } else {
                if (isset($orderServiceResponse['message'])) {
                    $message = $message . ": " . $orderServiceResponse['message'];
                }
                throw new LocalizedException(__($message));
            }
        } catch (\Throwable $exception) {
            $this->quoteStateManager->recover($quote);
            throw $exception;
        }
        return true;
    }

    /**
     * Get Payment source details
     *
     * @param array $paymentSource
     * @return PaymentSourceDetailsInterface
     */
    private function getPaymentSourceDetails(array $paymentSource): PaymentSourceDetailsInterface
    {
        /** @var PaymentSourceDetailsInterface $paymentDetails */
        $paymentDetails = $this->paymentSourceDetailsFactory->create();
        $paymentDetails->setCard($this->getPaymentCardDetails($paymentSource['card']));
        return $paymentDetails;
    }

    /**
     * Get Payment card details
     *
     * @param array $paymentCard
     * @return PaymentCardDetailsInterface
     */
    private function getPaymentCardDetails(array $paymentCard): PaymentCardDetailsInterface
    {
        /** @var PaymentCardDetailsInterface $paymentCardDetails */
        $paymentCardDetails = $this->paymentCardDetailsFactory->create();
        $paymentCardDetails->setName($paymentCard['name']);
        $paymentCardDetails->setLastDigits($paymentCard['last_digits']);
        if (isset($paymentCard['card_expiry_month'])) {
            $paymentCardDetails->setCardExpiryMonth($paymentCard['card_expiry_month']);
        }
        if (isset($paymentCard['card_expiry_year'])) {
            $paymentCardDetails->setCardExpiryYear($paymentCard['card_expiry_year']);
        }
        if (!empty($paymentCard['bin_details'])) {
            $paymentCardDetails->setBinDetails($this->getPaymentCardBinDetails($paymentCard['bin_details']));
        }
        if (isset($paymentCard['authentication_result'])
            && $paymentCardDetails instanceof PaymentCardDetailsWithAuthenticationResultInterface
        ) {
            $paymentCardDetails->setAuthenticationResult(
                $this->getPaymentCardAuthenticationResultDetails($paymentCard['authentication_result'])
            );
        }
        return $paymentCardDetails;
    }

    /**
     * Get Payment card bin details
     *
     * @param array $paymentCardBin
     * @return PaymentCardBinDetailsInterface
     */
    private function getPaymentCardBinDetails(array $paymentCardBin): PaymentCardBinDetailsInterface
    {
        /** @var PaymentCardBinDetailsInterface $paymentCardBinDetails */
        $paymentCardBinDetails = $this->paymentCardBinDetailsFactory->create();
        $paymentCardBinDetails->setBin($paymentCardBin['bin']);
        return $paymentCardBinDetails;
    }

    /**
     * Get Payment card authentication result details
     *
     * @param array $paymentCardAuthenticationResult
     * @return PaymentCardAuthenticationResultDetailsInterface
     */
    private function getPaymentCardAuthenticationResultDetails(
        array $paymentCardAuthenticationResult
    ): PaymentCardAuthenticationResultDetailsInterface {
        /** @var PaymentCardAuthenticationResultDetailsInterface $paymentCardAuthResultDetails */
        $paymentCardAuthResultDetails = $this->paymentCardAuthResultDetailsFactory->create();
        $paymentCardAuthResultDetails->setLiabilityShift(
            $this->resolveLiabilityShift($paymentCardAuthenticationResult['liability_shift'] ?? null)
        );

        return $paymentCardAuthResultDetails;
    }

    /**
     * Validate and resolve Liability Shift value
     *
     * @param string|null $liabilityShift
     * @return string|null
     */
    private function resolveLiabilityShift(?string $liabilityShift): ?string
    {
        if ($liabilityShift === null || $liabilityShift === '') {
            return null;
        }

        $liabilityShift = strtoupper(trim($liabilityShift));

        return in_array($liabilityShift, self::LIABILITY_SHIFT, true)
            ? $liabilityShift
            : null;
    }
}
