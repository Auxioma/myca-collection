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

namespace Magento\PaymentServicesPaypalGraphQl\Test\Unit\Model\Resolver;

use Magento\Framework\DataObject;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Phrase;
use Magento\PaymentServicesPaypal\Api\CompleteOrderInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderDetailsInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderInterface;
use Magento\PaymentServicesPaypal\Api\PaymentOrderManagementInterface;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\PaymentServicesPaypalGraphQl\Model\Resolver\CompleteOrderResolver;
use Magento\PaymentServicesPaypalGraphQl\Model\Resolver\CreatePaymentOrder;
use Magento\PaymentServicesPaypalGraphQl\Model\Resolver\GetPaymentOrder;
use Magento\PaymentServicesPaypalGraphQl\Model\Resolver\SetCartAsInactive;
use Magento\PaymentServicesPaypalGraphQl\Model\Resolver\SyncPaymentOrder;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\SalesGraphQl\Model\Formatter\Order as OrderFormatter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CartAuthorizationTest extends TestCase
{
    private const MASKED_CART_ID = 'masked-cart-id';
    private const CART_ID = 42;
    private const CUSTOMER_ID = 7;
    private const STORE_ID = 3;

    private Field&MockObject $field;
    private ResolveInfo&MockObject $resolveInfo;

    protected function setUp(): void
    {
        $this->field = $this->createMock(Field::class);
        $this->resolveInfo = $this->createMock(ResolveInfo::class);
    }

    public function testCreatePaymentOrderUsesAuthorizedCart(): void
    {
        $getCartForUser = $this->authorizedCart();
        $management = $this->createMock(PaymentOrderManagementInterface::class);
        $expected = $this->createMock(PaymentOrderInterface::class);
        $management->expects(self::once())
            ->method('create')
            ->with('method', 'paypal', self::CART_ID, 'CHECKOUT', false, self::CUSTOMER_ID, null)
            ->willReturn($expected);

        $resolver = new CreatePaymentOrder($management, $getCartForUser, $this->createMock(Config::class));

        self::assertSame($expected, $resolver->resolve(
            $this->field,
            $this->context(),
            $this->resolveInfo,
            null,
            ['input' => [
                'cartId' => self::MASKED_CART_ID,
                'methodCode' => 'method',
                'paymentSource' => 'paypal',
                'location' => 'CHECKOUT'
            ]]
        ));
    }

    public function testCreatePaymentOrderStopsWhenCartIsUnauthorized(): void
    {
        $management = $this->createMock(PaymentOrderManagementInterface::class);
        $management->expects(self::never())->method('create');

        $resolver = new CreatePaymentOrder(
            $management,
            $this->unauthorizedCart(),
            $this->createMock(Config::class)
        );

        $this->expectException(GraphQlAuthorizationException::class);
        $resolver->resolve($this->field, $this->context(), $this->resolveInfo, null, [
            'input' => [
                'cartId' => self::MASKED_CART_ID,
                'methodCode' => 'method',
                'paymentSource' => 'paypal',
                'location' => 'CHECKOUT'
            ]
        ]);
    }

    public function testGetPaymentOrderUsesAuthorizedCart(): void
    {
        $management = $this->createMock(PaymentOrderManagementInterface::class);
        $expected = $this->createMock(PaymentOrderDetailsInterface::class);
        $management->expects(self::once())
            ->method('get')
            ->with(self::CART_ID, 'paypal-order-id', self::CUSTOMER_ID)
            ->willReturn($expected);

        $resolver = new GetPaymentOrder($management, $this->authorizedCart());

        self::assertSame($expected, $resolver->resolve(
            $this->field,
            $this->context(),
            $this->resolveInfo,
            null,
            ['cartId' => self::MASKED_CART_ID, 'id' => 'paypal-order-id']
        ));
    }

    public function testGetPaymentOrderStopsWhenCartIsUnauthorized(): void
    {
        $management = $this->createMock(PaymentOrderManagementInterface::class);
        $management->expects(self::never())->method('get');
        $resolver = new GetPaymentOrder($management, $this->unauthorizedCart());

        $this->expectException(GraphQlAuthorizationException::class);
        $resolver->resolve($this->field, $this->context(), $this->resolveInfo, null, [
            'cartId' => self::MASKED_CART_ID,
            'id' => 'paypal-order-id'
        ]);
    }

    public function testSyncPaymentOrderUsesAuthorizedCart(): void
    {
        $management = $this->createMock(PaymentOrderManagementInterface::class);
        $management->expects(self::once())
            ->method('sync')
            ->with(self::CART_ID, 'paypal-order-id', self::CUSTOMER_ID)
            ->willReturn(true);

        $resolver = new SyncPaymentOrder($management, $this->authorizedCart());

        self::assertTrue($resolver->resolve(
            $this->field,
            $this->context(),
            $this->resolveInfo,
            null,
            ['input' => ['cartId' => self::MASKED_CART_ID, 'id' => 'paypal-order-id']]
        ));
    }

    public function testSyncPaymentOrderStopsWhenCartIsUnauthorized(): void
    {
        $management = $this->createMock(PaymentOrderManagementInterface::class);
        $management->expects(self::never())->method('sync');
        $resolver = new SyncPaymentOrder($management, $this->unauthorizedCart());

        $this->expectException(GraphQlAuthorizationException::class);
        $resolver->resolve($this->field, $this->context(), $this->resolveInfo, null, [
            'input' => ['cartId' => self::MASKED_CART_ID, 'id' => 'paypal-order-id']
        ]);
    }

    public function testCompleteOrderUsesAuthorizedCart(): void
    {
        $completeOrder = $this->createMock(CompleteOrderInterface::class);
        $completeOrder->expects(self::once())
            ->method('syncAndPlaceOrder')
            ->with(self::CART_ID, 'paypal-order-id')
            ->willReturn(84);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('000000084');
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects(self::once())->method('get')->with(84)->willReturn($order);
        $formatter = $this->createMock(OrderFormatter::class);
        $formatter->method('format')->with($order)->willReturn(['number' => '000000084']);

        $resolver = new CompleteOrderResolver(
            $completeOrder,
            $this->authorizedCart(),
            $orderRepository,
            $formatter
        );
        $result = $resolver->resolve($this->field, $this->context(), $this->resolveInfo, null, [
            'input' => ['cartId' => self::MASKED_CART_ID, 'id' => 'paypal-order-id']
        ]);

        self::assertSame('000000084', $result['order']['order_number']);
    }

    public function testCompleteOrderStopsWhenCartIsUnauthorized(): void
    {
        $completeOrder = $this->createMock(CompleteOrderInterface::class);
        $completeOrder->expects(self::never())->method('syncAndPlaceOrder');
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects(self::never())->method('get');
        $resolver = new CompleteOrderResolver(
            $completeOrder,
            $this->unauthorizedCart(),
            $orderRepository,
            $this->createMock(OrderFormatter::class)
        );

        $this->expectException(GraphQlAuthorizationException::class);
        $resolver->resolve($this->field, $this->context(), $this->resolveInfo, null, [
            'input' => ['cartId' => self::MASKED_CART_ID, 'id' => 'paypal-order-id']
        ]);
    }

    public function testSetCartAsInactiveUsesAuthorizedCart(): void
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getId')->willReturn(self::CART_ID);
        $cart->method('getIsActive')->willReturn(true);
        $cart->expects(self::once())->method('setIsActive')->with(false)->willReturnSelf();
        $getCartForUser = $this->expectCartLookup($cart);
        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with($cart);

        $resolver = new SetCartAsInactive($repository, $getCartForUser, $this->createMock(LoggerInterface::class));
        $result = $resolver->resolve($this->field, $this->context(), $this->resolveInfo, null, [
            'cartId' => self::MASKED_CART_ID
        ]);

        self::assertTrue($result['success']);
    }

    public function testSetCartAsInactiveStopsWhenCartIsUnauthorized(): void
    {
        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->expects(self::never())->method('save');
        $resolver = new SetCartAsInactive(
            $repository,
            $this->unauthorizedCart(),
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(GraphQlAuthorizationException::class);
        $resolver->resolve($this->field, $this->context(), $this->resolveInfo, null, [
            'cartId' => self::MASKED_CART_ID
        ]);
    }

    private function context(): DataObject
    {
        return new DataObject([
            'user_id' => self::CUSTOMER_ID,
            'extension_attributes' => new DataObject([
                'store' => new DataObject(['id' => self::STORE_ID])
            ])
        ]);
    }

    private function authorizedCart(): GetCartForUser&MockObject
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getId')->willReturn(self::CART_ID);
        return $this->expectCartLookup($cart);
    }

    private function expectCartLookup(Quote $cart): GetCartForUser&MockObject
    {
        $getCartForUser = $this->createMock(GetCartForUser::class);
        $getCartForUser->expects(self::once())
            ->method('execute')
            ->with(self::MASKED_CART_ID, self::CUSTOMER_ID, self::STORE_ID)
            ->willReturn($cart);
        return $getCartForUser;
    }

    private function unauthorizedCart(): GetCartForUser&MockObject
    {
        $getCartForUser = $this->createMock(GetCartForUser::class);
        $getCartForUser->expects(self::once())
            ->method('execute')
            ->with(self::MASKED_CART_ID, self::CUSTOMER_ID, self::STORE_ID)
            ->willThrowException(new GraphQlAuthorizationException(new Phrase('denied')));
        return $getCartForUser;
    }
}
