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

namespace PayPal\Braintree\Model\AccountUpdater;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use PayPal\Braintree\Api\Data\NotificationInterface;

class Diff
{
    public const MAGENTO_BRAINTREE_CARD_TYPE_LOOKUP = [
        'MC' => 'MasterCard',
        'VI' => 'Visa',
        'DI' => 'Discover'
    ];

    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * If expiration, last 4 or card type have changed - return true.
     *
     * @param PaymentTokenInterface $token
     * @param NotificationInterface $notification
     * @return bool
     * @throws LocalizedException
     */
    public function execute(PaymentTokenInterface $token, NotificationInterface $notification): bool
    {
        $tokenDetails = $this->serializer->unserialize($token->getTokenDetails());
        if (!is_array($tokenDetails) ||
            empty($tokenDetails) ||
            !isset(self::MAGENTO_BRAINTREE_CARD_TYPE_LOOKUP[$tokenDetails['type']])
        ) {
            throw new LocalizedException(__("A problem occurred when loading the payment token details"));
        }

        return $notification->getOldExpiration() !== $notification->getNewExpiration() ||
            $notification->getOldLast4() !== $notification->getNewLast4() ||
            self::MAGENTO_BRAINTREE_CARD_TYPE_LOOKUP[$tokenDetails['type']] !== $notification->getNewCardType();
    }
}
