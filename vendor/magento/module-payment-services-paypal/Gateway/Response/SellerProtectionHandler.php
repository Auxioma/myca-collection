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

namespace Magento\PaymentServicesPaypal\Gateway\Response;

use InvalidArgumentException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class SellerProtectionHandler implements HandlerInterface
{
    public const SELLER_PROTECTION_KEY = 'seller_protection';
    public const SELLER_PROTECTION_STATUS_KEY = 'seller_protection_status';
    public const SELLER_PROTECTION_DISPUTE_CATEGORIES_KEY = 'seller_protection_dispute_categories';

    /**
     * Handles seller protection eligibility
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new InvalidArgumentException('Payment data object should be provided');
        }

        $sellerProtection = $response['mp-transaction'][self::SELLER_PROTECTION_KEY] ?? null;
        if ($sellerProtection === null) {
            return;
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];
        $payment = $paymentDO->getPayment();

        if (isset($sellerProtection['status'])) {
            $payment->setAdditionalInformation(
                self::SELLER_PROTECTION_STATUS_KEY,
                $sellerProtection['status']
            );
        }
    }
}
