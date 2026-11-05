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

namespace PayPal\Braintree\Controller\Shipping;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use PayPal\Braintree\Api\ShippingCallbackServiceInterface;

class Callback implements HttpPostActionInterface, HttpGetActionInterface, CsrfAwareActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param ShippingCallbackServiceInterface $shippingCallbackService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        protected readonly ShippingCallbackServiceInterface $shippingCallbackService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $cartId = $this->request->getParam('cart_id');
            $requestBody = $this->request->getContent();

            if (!$cartId || !$requestBody) {
                throw new LocalizedException(__('Missing required parameters'));
            }

            $this->logger->debug('PayPal shipping callback received', [
                'cart_id' => $cartId,
                'body' => $requestBody
            ]);

            $response = $this->shippingCallbackService->execute(
                $cartId,
                $requestBody
            );

            $this->logger->debug('Merchant Response', [
                $response
            ]);

            $result->setHttpResponseCode(200);
            $result->setData($response);

        } catch (Exception $e) {
            $this->logger->error(
                'PayPal shipping callback error: ' . $e->getMessage(),
                ['exception' => $e]
            );

            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'COUNTRY_ERROR')) {
                $issueCode = 'COUNTRY_ERROR';
            } elseif (str_contains($errorMessage, 'STATE_ERROR')) {
                $issueCode = 'STATE_ERROR';
            } elseif (str_contains($errorMessage, 'ZIP_ERROR')) {
                $issueCode = 'ZIP_ERROR';
            } elseif (str_contains($errorMessage, 'METHOD_UNAVAILABLE')) {
                $issueCode = 'METHOD_UNAVAILABLE';
            } else {
                $issueCode = 'ADDRESS_ERROR';
            }

            $result->setHttpResponseCode(422);
            $result->setData([
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [
                    [
                        'issue' => $issueCode,
                    ]
                ]
            ]);
        }

        return $result;
    }

    /**
     * Create exception in case CSRF validation failed.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Bypass CSRF validation for PayPal callbacks
     *
     * @param RequestInterface $request
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }
}
