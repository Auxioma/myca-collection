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

namespace PayPal\Braintree\Block\Onepage;

use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Model\Lpm\Ui\ConfigProvider;
use PayPal\Braintree\Model\Lpm\Config;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * @api
 */
class PuiSuccess extends Template
{
    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Session $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Is order placed using Pay Upon Invoice LPM?
     *
     * @return bool
     */
    public function isPayUponInvoiceLpmAvailable(): bool
    {
        if ($this->getOrder()) {
            $payment = $this->getOrder()->getPayment();
            if ($payment instanceof Payment && $payment->getMethod() === ConfigProvider::METHOD_CODE) {
                $additionalInfo = $payment->getAdditionalInformation();
                if (array_key_exists('fundingSource', $additionalInfo) &&
                    $additionalInfo['fundingSource'] === Config::VALUE_PAY_UPON_INVOICE
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get order
     *
     * @return OrderInterface|null
     */
    private function getOrder(): ?OrderInterface
    {
        $orderId = $this->checkoutSession->getLastRealOrder()->getId();
        if ($orderId) {
            return $this->orderRepository->get($orderId);
        }

        return null;
    }

    /**
     * Get LPM email address
     *
     * @return string|null
     */
    public function getLpmEmail(): ?string
    {
        return $this->getOrder()->getCustomerEmail() ?? null;
    }
}
