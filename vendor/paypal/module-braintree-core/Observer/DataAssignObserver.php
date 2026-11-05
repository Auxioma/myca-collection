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
namespace PayPal\Braintree\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    public const PAYMENT_METHOD_NONCE = 'payment_method_nonce';
    public const DEVICE_DATA = 'device_data';
    public const CAPTCHA_RESPONSE = 'g-recaptcha-response';
    public const PAYMENT_ID = 'paymentId';
    public const FUNDING_SOURCE = 'fundingSource';

    /**
     * @var array
     */
    protected $additionalInformationList = [
        self::PAYMENT_METHOD_NONCE,
        self::DEVICE_DATA,
        self::CAPTCHA_RESPONSE,
        self::PAYMENT_ID,
        self::FUNDING_SOURCE
    ];

    /**
     * Assign additional payment information
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
