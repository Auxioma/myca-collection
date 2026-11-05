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

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesPaypal\Model\CancellationService;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Exception;
use Throwable;

class PlaceOrder implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param MessageManagerInterface $messageManager
     * @param Checkout $checkout
     * @param UrlInterface $url
     * @param CancellationService $cancellationService
     * @param QuoteStateManager|null $quoteStateManager
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly MessageManagerInterface $messageManager,
        private readonly Checkout $checkout,
        private readonly UrlInterface $url,
        private readonly CancellationService $cancellationService,
        private ?QuoteStateManager $quoteStateManager = null
    ) {
        $this->quoteStateManager = $quoteStateManager
            ?: ObjectManager::getInstance()->get(QuoteStateManager::class);
    }

    /**
     * @inheritdoc
     */
    public function execute() : ResponseInterface
    {
        try {
            $this->checkout->validateQuote();
            $this->checkout->placeOrder();
            $this->response->setRedirect($this->url->getUrl($this->checkout->getSuccessPageUri()));
        } catch (LocalizedException $e) {
            $recovered = $this->recoverQuoteState();
            $canceled = $this->cancellationService->execute((int) $this->checkout->getQuote()->getId());
            $this->processException(
                $e,
                $recovered ? $e->getMessage() : __('Unable to restore the cart. Please try again.')->getText(),
                $canceled
            );
        } catch (Exception $e) {
            $recovered = $this->recoverQuoteState();
            $canceled = $this->cancellationService->execute((int) $this->checkout->getQuote()->getId());
            $this->processException(
                $e,
                $recovered
                    ? __('We can\'t process the order right now. Please try again later.')->getText()
                    : __('Unable to restore the cart. Please try again.')->getText(),
                $canceled
            );
        }
        return $this->response;
    }

    /**
     * Recover SSSC state without hiding the original placement response.
     *
     * @return bool
     */
    private function recoverQuoteState(): bool
    {
        try {
            if ($this->checkout->getLocation() === $this->checkout::LOCATION_PRODUCT_PAGE) {
                return true;
            }
            $this->quoteStateManager->recover($this->checkout->getQuote());
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request) :? InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request) :? bool
    {
        return true;
    }

    /**
     * Process exception.
     *
     * @param Exception $exception
     * @param string $message
     * @param bool $canceled
     * @return void
     */
    private function processException(Exception $exception, string $message, bool $canceled) : void
    {
        $this->messageManager->addExceptionMessage(
            $exception,
            $message
        );
        $this->response->setRedirect($this->url->getUrl($canceled ? 'checkout/cart' : '*/*/review'));
    }
}
