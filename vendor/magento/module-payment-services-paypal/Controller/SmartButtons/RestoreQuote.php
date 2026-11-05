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

namespace Magento\PaymentServicesPaypal\Controller\SmartButtons;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Psr\Log\LoggerInterface;
use Throwable;

class RestoreQuote implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param QuoteStateManager $quoteStateManager
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteStateManager $quoteStateManager,
        private readonly JsonFactory $jsonFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Restore quote values changed by an aborted SSSC attempt.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            if ((int) $this->checkoutSession->getQuoteId() <= 0) {
                return $result->setData(['success' => true, 'restored' => false]);
            }

            $restored = $this->quoteStateManager->restore($this->checkoutSession->getQuote());

            return $result->setData([
                'success' => true,
                'restored' => $restored
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to restore quote after an aborted PayPal attempt.', [
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode()
            ]);

            return $result->setHttpResponseCode(500)->setData(['success' => false]);
        }
    }

    /**
     * Return no custom exception so Magento can handle an invalid form key.
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
     * Defer CSRF validation to Magento's standard form-key validator.
     *
     * @param RequestInterface $request
     * @return bool|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return null;
    }
}
