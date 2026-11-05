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

namespace PayPal\Braintree\Test\Unit\Model\Paypal\Helper;

use InvalidArgumentException;
use Magento\Directory\Model\Region;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Paypal\Helper\QuoteUpdater;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteUpdaterTest extends TestCase
{
    /**
     * @var QuoteUpdater
     */
    private QuoteUpdater $quoteUpdater;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $configMock;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private CartRepositoryInterface|MockObject $quoteRepositoryMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private ManagerInterface|MockObject $messageManagerMock;

    /**
     * @var ResourceConnection|MockObject
     */
    private ResourceConnection|MockObject $resourceConnectionMock;

    /**
     * @var Region|MockObject
     */
    private Region|MockObject $regionMock;

    protected function setUp(): void
    {
        $this->configMock = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $this->quoteRepositoryMock = $this->getMockBuilder(CartRepositoryInterface::class)->getMock();
        $this->messageManagerMock = $this->getMockBuilder(ManagerInterface::class)->getMock();
        $this->resourceConnectionMock = $this->getMockBuilder(ResourceConnection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->regionMock = $this->getMockBuilder(Region::class)->disableOriginalConstructor()->getMock();

        $this->quoteUpdater = new QuoteUpdater(
            $this->configMock,
            $this->quoteRepositoryMock,
            $this->messageManagerMock,
            $this->resourceConnectionMock,
            $this->regionMock
        );
    }

    public function testExecuteException()
    {
        $this->markTestSkipped('Skip this test');
        $this->expectException(InvalidArgumentException::class);
        $this->quoteUpdater->execute('', [], $this->getQuoteMock());
    }

    /**
     * @return Quote|MockObject
     */
    private function getQuoteMock()
    {
        return $this->getMockBuilder(Quote::class)
            ->onlyMethods([
                'collectTotals',
                'getBillingAddress',
                'getShippingAddress',
                'getIsVirtual'
            ])
            ->disableOriginalConstructor()
            ->getMock();
    }
}
