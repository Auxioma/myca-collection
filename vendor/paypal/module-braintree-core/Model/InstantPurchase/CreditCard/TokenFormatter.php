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

namespace PayPal\Braintree\Model\InstantPurchase\CreditCard;

use Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Braintree vaulted credit cards formatter
 *
 * Class TokenFormatter
 */
class TokenFormatter implements PaymentTokenFormatterInterface
{
    /**
     * @var array
     */
    public static array $baseCardTypes = [
        'VI' => 'Visa',
        'MC' => 'MasterCard',
        'AE' => 'American Express',
        'DI' => 'Discover',
        'JCB' => 'JCB',
        'MI' => 'Maestro',
        'DN' => 'Diners Club',
        'UPD' => 'Union Pay through Discover',
        'ELO' => 'ELO'
    ];

    /**
     * @inheritdoc
     */
    public function formatPaymentToken(PaymentTokenInterface $paymentToken): string
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        if (!isset($details['type'], $details['maskedCC'], $details['expirationDate'])) {
            throw new \InvalidArgumentException('Invalid Braintree credit card token details.');
        }

        $ccType = self::$baseCardTypes[$details['type']] ?? $details['type'];

        return sprintf(
            '%s: %s, %s: %s (%s: %s)',
            __('Credit Card'),
            $ccType,
            __('ending'),
            $details['maskedCC'],
            __('expires'),
            $details['expirationDate']
        );
    }
}
