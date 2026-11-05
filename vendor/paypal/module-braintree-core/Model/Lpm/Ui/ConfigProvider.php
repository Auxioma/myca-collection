<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
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

namespace PayPal\Braintree\Model\Lpm\Ui;

use PayPal\Braintree\Block\Widget\LpmDob;
use PayPal\Braintree\Model\Lpm\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const METHOD_CODE = 'braintree_local_payment';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LpmDob
     */
    private LpmDob $dob;

    /**
     * @var ResolverInterface
     */
    private ResolverInterface $resolver;

    /**
     * ConfigProvider constructor.
     *
     * @param Config $config
     * @param LpmDob $dob
     * @param ResolverInterface $resolver
     */
    public function __construct(Config $config, LpmDob $dob, ResolverInterface $resolver)
    {
        $this->config = $config;
        $this->dob = $dob;
        $this->resolver = $resolver;
    }

    /**
     * Get config
     *
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getConfig(): array
    {
        if (!$this->config->isActive()) {
            return [];
        }

        return [
            'payment' => [
                self::METHOD_CODE => [
                    'allowedMethods' => $this->config->getAllowedMethods(),
                    'clientToken' => $this->config->getClientToken(),
                    'merchantAccountId' => $this->config->getMerchantAccountId(),
                    'paymentIcons' => $this->config->getPaymentIcons(),
                    'title' => $this->config->getTitle(),
                    'fallbackUrl' => $this->config->getFallbackUrl(),
                    'fallbackButtonText' => $this->config->getFallbackButtonText(),
                    'dob' => $this->dob->toHtml(),
                    'dobConfig' => $this->dob->getDobConfig(),
                    'locale' => $this->resolver->getLocale()
                ]
            ]
        ];
    }
}
