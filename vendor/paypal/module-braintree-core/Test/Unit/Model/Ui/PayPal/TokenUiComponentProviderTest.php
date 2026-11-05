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
namespace PayPal\Braintree\Test\Unit\Model\Ui\PayPal;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\DataResolver\Customer\GetCustomerIdByPaymentTokenInterface;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\Ui\PayPal\TokenUiComponentProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject as MockObject;
use Psr\Log\LoggerInterface;

class TokenUiComponentProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var UrlInterface|MockObject
     */
    private UrlInterface|MockObject $urlBuilder;

    /**
     * @var PaymentTokenInterface|MockObject
     */
    private PaymentTokenInterface|MockObject $paymentToken;

    /**
     * @var TokenUiComponentInterface|MockObject
     */
    private TokenUiComponentInterface|MockObject $tokenComponent;

    /**
     * @var TokenUiComponentInterfaceFactory|MockObject
     */
    private TokenUiComponentInterfaceFactory|MockObject $componentFactory;

    /**
     * @var TokenUiComponentProvider|MockObject
     */
    private TokenUiComponentProvider|MockObject $componentProvider;

    /**
     * @var SerializerInterface|MockObject
     */
    private SerializerInterface|MockObject $serializer;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->componentFactory = $this->getMockBuilder(TokenUiComponentInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $braintreeConfig = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $braintreeGetCustomerIdByPaymentToken = $this->getMockBuilder(GetCustomerIdByPaymentTokenInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $braintreeAdapter = $this->getMockBuilder(BraintreeAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->tokenComponent = $this->createMock(TokenUiComponentInterface::class);

        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $this->paymentToken = $this->createMock(PaymentTokenInterface::class);

        $this->serializer = $this->createMock(SerializerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);

        $this->componentProvider = new TokenUiComponentProvider(
            $this->componentFactory,
            $braintreeConfig,
            $braintreeGetCustomerIdByPaymentToken,
            $braintreeAdapter,
            $this->urlBuilder,
            $this->serializer,
            $logger
        );
    }

    /**
     * @covers \PayPal\Braintree\Model\Ui\PayPal\TokenUiComponentProvider::getComponentForToken
     */
    public function testGetComponentForToken()
    {
        $tokenDetails = [
            'payerEmail' => 'john.doe@example.com'
        ];
        $hash = '4g1mn4ew0vj23n2jf';

        $this->serializer->expects(self::once())->method('unserialize')->willReturn($tokenDetails);

        $this->componentFactory->expects(static::once())
            ->method('create')
            ->willReturn($this->tokenComponent);

        $this->paymentToken->expects(static::once())
            ->method('getPublicHash')
            ->willReturn($hash);

        $this->urlBuilder->expects(static::once())
            ->method('getUrl');

        $actual = $this->componentProvider->getComponentForToken($this->paymentToken);

        static::assertEquals($this->tokenComponent, $actual);
    }

    /**
     * @covers \PayPal\Braintree\Model\Ui\PayPal\TokenUiComponentProvider::getComponentForToken
     */
    public function testGetCustomerComponentForTokenWithBraintreeCustomerId()
    {
        $tokenDetails = [
            'payerEmail' => 'john.doe@example.com',
            'customerId' => '1234322'
        ];
        $hash = '4g1mn4ew0vj23n2jf';

        $this->serializer->expects(self::once())->method('unserialize')->willReturn($tokenDetails);

        $this->componentFactory->expects(static::once())
            ->method('create')
            ->willReturn($this->tokenComponent);

        $this->paymentToken->expects(static::once())
            ->method('getPublicHash')
            ->willReturn($hash);

        $this->urlBuilder->expects(static::once())
            ->method('getUrl');

        $actual = $this->componentProvider->getComponentForToken($this->paymentToken);

        self::assertEquals($this->tokenComponent, $actual);
    }
}
