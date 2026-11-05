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

namespace Magento\PaymentServicesPaypal\Controller\SmartButtons;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\AddressConverter;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\Framework\UrlInterface;
use Magento\Framework\Exception\LocalizedException;
use Exception;

class UpdateQuote implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param OrderService $orderService
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param Checkout $checkout
     * @param AddressConverter $addressConverter
     * @param UrlInterface $url
     * @param QuoteStateManager|null $quoteStateManager
     */
    public function __construct(
        private readonly OrderService $orderService,
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly Checkout $checkout,
        private readonly AddressConverter $addressConverter,
        private readonly UrlInterface $url,
        private ?QuoteStateManager $quoteStateManager = null
    ) {
        $this->quoteStateManager = $quoteStateManager
            ?: ObjectManager::getInstance()->get(QuoteStateManager::class);
    }

    /**
     * Execute quote update
     *
     * @return ResultInterface
     */
    public function execute() : ResultInterface
    {
        $error = false;
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $locationOverride = $this->request->getPost('location');
            if ($locationOverride && $locationOverride === $this->checkout::LOCATION_PRODUCT_PAGE) {
                $this->checkout->setLocation($locationOverride);
            }
            $location = $this->checkout->getLocation();
            if ($location !== $this->checkout::LOCATION_PRODUCT_PAGE) {
                $this->checkout->unsetQuote();
            }
            $this->checkout->validateQuote();
            try {
                $quote = $this->checkout->getQuote();
                $storeId = $quote->getStoreId();
                $orderId = $quote->getPayment()?->getAdditionalInformation('paypal_order_id');
                if (empty($orderId)) {
                    throw new LocalizedException(__('PayPal order not found. Please try again.'));
                }
                $order = $this->orderService->get((string) $storeId, $orderId);
                $this->checkout->updateQuote(
                    $this->addressConverter->convertShippingAddress($order),
                    $this->addressConverter->convertBillingAddress($order),
                    $orderId,
                    $this->request->getParam('paypal_payer_id', ''),
                    $order['paypal-order']['mp_order_id'],
                    $location
                );
            } catch (LocalizedException | Exception $e) {
                if ($location !== $this->checkout::LOCATION_PRODUCT_PAGE) {
                    $this->quoteStateManager->recover($this->checkout->getQuote());
                }
                $error = __('Can\'t update quote. Please try again.');
            }
        } catch (LocalizedException $e) {
            $error = $e->getMessage();
        }
        if (!$error) {
            $result->setHttpResponseCode(200)
                ->setData(
                    [
                        'success' => true,
                        'redirectUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons/review')
                    ]
                );
        } else {
            $result->setHttpResponseCode(500)
                ->setData(
                    [
                        'success' => false,
                        'error' => $error
                    ]
                );
        }
        return $result;
    }

    /**
     * Override for CsrfValidationException method
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request) :? InvalidRequestException
    {
        return null;
    }

    /**
     * Override for CsrfValidation method
     *
     * @param RequestInterface $request
     * @return bool|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request) :? bool
    {
        return true;
    }
}
