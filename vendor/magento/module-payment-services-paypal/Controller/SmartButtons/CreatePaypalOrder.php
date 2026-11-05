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
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesBase\Model\HttpException;
use Exception;
use Throwable;

class CreatePaypalOrder implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param Checkout $checkout
     * @param QuoteStateManager|null $quoteStateManager
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly Checkout $checkout,
        private ?QuoteStateManager $quoteStateManager = null
    ) {
        $this->quoteStateManager = $quoteStateManager
            ?: ObjectManager::getInstance()->get(QuoteStateManager::class);
    }

    /**
     * Dispatch the order creation request with Commerce params
     *
     * @return ResultInterface
     */
    public function execute() : ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $paymentSource = $this->request->getPost('payment_source');
        $threeDSMode = $this->request->getPost('three_ds_mode');
        $location = $this->request->getPost('location');

        try {
            $this->checkout->setLocation($location);
            if ($location !== $this->checkout::LOCATION_PRODUCT_PAGE) {
                $this->checkout->unsetQuote();
            }
        } catch (LocalizedException $e) {
            return $this->createErrorResult($result, $e->getMessage());
        }

        try {
            $this->checkout->validateQuote();
        } catch (LocalizedException $e) {
            return $this->createErrorResult($result, $e->getMessage());
        } catch (Exception) {
            return $this->createErrorResult($result, (string) __('Can\'t create PayPal order. Please try again.'));
        }

        try {
            $response = $this->checkout->createPayPalOrder($paymentSource, $threeDSMode);
            if (!$response['is_successful']) {
                throw new HttpException('Failed to create an order.');
            }
        } catch (Exception) {
            if ($location !== $this->checkout::LOCATION_PRODUCT_PAGE) {
                try {
                    $this->quoteStateManager->recover($this->checkout->getQuote());
                } catch (Throwable) {
                    // QuoteStateManager logs recovery failures using sanitized context.
                    return $this->createErrorResult(
                        $result,
                        (string) __('Can\'t create PayPal order. Please try again.')
                    );
                }
            }

            return $this->createErrorResult($result, (string) __('Can\'t create PayPal order. Please try again.'));
        }

        return $result->setHttpResponseCode($response['status'])
            ->setData(['response' => $response]);
    }

    /**
     * Create a failed order-creation response.
     *
     * @param ResultInterface $result
     * @param string $error
     * @return ResultInterface
     */
    private function createErrorResult(ResultInterface $result, string $error): ResultInterface
    {
        return $result->setHttpResponseCode(500)
            ->setData([
                'response' => [
                    'error' => $error
                ]
            ]);
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request) :? InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request) :? bool
    {
        return true;
    }
}
