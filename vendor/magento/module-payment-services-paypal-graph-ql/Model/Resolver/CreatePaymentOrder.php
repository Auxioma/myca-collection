<?php

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2023 Adobe
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

namespace Magento\PaymentServicesPaypalGraphQl\Model\Resolver;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\PaymentServicesPaypal\Api\PaymentOrderManagementInterface;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\PaymentServicesPaypal\Model\GooglePayConfigProvider;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

/**
 * Create Payment Order resolver, used for GraphQL mutation processing.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */

class CreatePaymentOrder implements ResolverInterface
{
    /**
     * @param PaymentOrderManagementInterface $paymentOrderManagement
     * @param GetCartForUser $getCartForUser
     * @param Config|null $config
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly PaymentOrderManagementInterface $paymentOrderManagement,
        private readonly GetCartForUser $getCartForUser,
        private ?Config $config = null
    ) {
        $this->config = $config ?: ObjectManager::getInstance()->get(Config::class);
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $customerId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($args['input']['cartId'], $customerId, $storeId);
        $cartId = (int)$cart->getId();
        $methodCode = $args['input']['methodCode'];
        $paymentSource = $args['input']['paymentSource'];
        $location = $args['input']['location'];
        $vaultIntent = $args['input']['vaultIntent'] ?? false;

        $threeDsMode = null;
        if ($paymentSource === GooglePayConfigProvider::PAYMENT_SOURCE) {
            $threeDsMode = $this->config->getGooglePayThreeDS();
        }

        return $this->paymentOrderManagement->create(
            $methodCode,
            $paymentSource,
            $cartId,
            $location,
            $vaultIntent,
            $customerId,
            $threeDsMode
        );
    }
}
