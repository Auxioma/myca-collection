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

namespace PayPal\Braintree\Block\Adminhtml\Order;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Adminhtml\Totals;
use Magento\Sales\Helper\Admin;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Model\Adminhtml\Source\Environment;

/**
 * @api
 */
class Braintree extends Totals
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param Admin $adminHelper
     * @param BraintreeConfig $braintreeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Admin $adminHelper,
        private readonly BraintreeConfig $braintreeConfig,
        array $data = []
    ) {
        parent::__construct($context, $registry, $adminHelper, $data);
    }

    /**
     * Render block HTML
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    protected function _toHtml(): string
    {
        $order = $this->getOrder();
        $merchantId = $this->getMerchantId();

        if (str_contains($order->getPayment()->getMethod(), 'braintree')
            && !empty($merchantId)
        ) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * Get braintree environment
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getEnvironment(): string
    {
        return $this->braintreeConfig->getEnvironment($this->getStoreId());
    }

    /**
     * Get Merchant Id
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getMerchantId(): ?string
    {
        return $this->braintreeConfig->getMerchantId($this->getStoreId());
    }

    /**
     * Generate braintree transaction link
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function generateBraintreeTransactionLink(): string
    {
        return sprintf(
            '%s/merchants/%s/transactions/%s',
            $this->getGatewayUrl(),
            $this->getMerchantId(),
            $this->getTransactionId()
        );
    }

    /**
     * Get braintree gateway url
     *
     * @throws NoSuchEntityException
     * @throws InputException
     */
    private function getGatewayUrl(): string
    {
        if ($this->getEnvironment() === Environment::ENVIRONMENT_SANDBOX) {
            return $this->braintreeConfig->getSandboxGatewayUrl();
        }

        return $this->braintreeConfig->getProductionGatewayUrl();
    }

    /**
     * Get braintree transaction ID
     *
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->getOrder()->getPayment()->getLastTransId() ?? '';
    }

    /**
     * Get store ID
     *
     * @return int
     */
    private function getStoreId(): int
    {
        return (int) $this->getOrder()->getStoreId();
    }
}
