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

namespace PayPal\Braintree\Test\Unit\Controller\Webhook;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\MessageQueue\PublisherInterface;
use PayPal\Braintree\Api\Data\NotificationInterfaceFactory;
use PayPal\Braintree\Controller\Webhook\AccountUpdater;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\RequestInterface;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\Webhook\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[CoversClass(AccountUpdater::class)]
#[CoversFunction('execute')]
class AccountUpdaterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Raw|MockObject
     */
    private Raw|MockObject $rawResult;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $moduleConfig;

    /**
     * @var RequestInterface|MockObject
     */
    private RequestInterface|MockObject $request;

    /**
     * @var AccountUpdater|MockObject
     */
    private AccountUpdater|MockObject $action;

    /**
     * Test setup
     *
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->rawResult = $this->getMockBuilder(Raw::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setHttpResponseCode'])
            ->getMock();

        $mockObjectManager = $this->createMock(ObjectManagerInterface::class);
        $mockObjectManager->method('create')
            ->willReturn($this->rawResult);

        $resultFactory = new ResultFactory($mockObjectManager);

        $this->moduleConfig = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isEnabled'])
            ->getMock();

        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->action = new AccountUpdater(
            $resultFactory,
            $this->moduleConfig,
            $this->request,
            $this->createMock(LoggerInterface::class),
            $this->createMock(BraintreeAdapter::class),
            $this->createMock(DirectoryList::class),
            $this->createMock(File::class)
        );
    }

    /**
     * Test Braintree returns 403 when feature is disabled
     *
     * @return void
     * @throws NotFoundException
     */
    public function testFeatureDisableReturns403()
    {
        $this->moduleConfig->method('isEnabled')
            ->willReturn(false);

        $this->rawResult->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(403);

        $result = $this->action->execute();
        $this->assertInstanceOf(ResultInterface::class, $result);
    }

    /**
     * Test missing params exception throw
     *
     * @return void
     * @throws NotFoundException
     */
    public function testTestMissingParamsThrowsException()
    {
        $this->moduleConfig->method('isEnabled')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn([]);

        $this->rawResult->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(400);

        $result = $this->action->execute();
        $this->assertInstanceOf(ResultInterface::class, $result);
    }
}
