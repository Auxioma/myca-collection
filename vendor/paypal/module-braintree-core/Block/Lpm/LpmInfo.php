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

namespace PayPal\Braintree\Block\Lpm;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\Braintree\Block\Info;

class LpmInfo extends Info
{
    /**
     * @param Context $context
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
    }

    /**
     * @var string
     */
    protected $_template = 'PayPal_Braintree::lpm/lpm-info.phtml';

    /**
     * Get the store name.
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreName(): string
    {
        return $this->storeManager->getStore()->getName();
    }
}
