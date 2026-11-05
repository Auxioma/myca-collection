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

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\PaymentServicesPaypal\Api\PaymentOrderManagementInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

/**
 * Sync Payment Order resolver, used for GraphQL mutation processing.
 */
class SyncPaymentOrder implements ResolverInterface
{
    /**
     * @var PaymentOrderManagementInterface
     */
    private PaymentOrderManagementInterface $paymentOrderManagement;

    /**
     * @var GetCartForUser
     */
    private GetCartForUser $getCartForUser;

    /**
     * @param PaymentOrderManagementInterface $paymentOrderManagement
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        PaymentOrderManagementInterface $paymentOrderManagement,
        GetCartForUser $getCartForUser
    ) {
        $this->paymentOrderManagement = $paymentOrderManagement;
        $this->getCartForUser = $getCartForUser;
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
        $id = $args['input']['id'];

        return $this->paymentOrderManagement->sync(
            $cartId,
            $id,
            $customerId
        );
    }
}
