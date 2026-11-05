<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaPaypal\Test\Unit\Model;

use Magento\Framework\Session\SessionManager;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\ReCaptchaPaypal\Model\ReCaptchaSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;

class ReCaptchaSessionTest extends TestCase
{
    use MockCreationTrait;

    /**
     * @var TimezoneInterface|MockObject
     */
    private $timezone;

    /**
     * @var SessionManager|MockObject
     */
    private $transparentSession;

    /**
     * @var SessionManager|MockObject
     */
    private $checkoutSession;

    /**
     * @var ReCaptchaSession
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->transparentSession = $this->createPartialMockWithReflection(
            SessionManager::class,
            ['getData', 'setData', 'unsetData']
        );
        $this->checkoutSession = $this->createPartialMockWithReflection(
            SessionManager::class,
            ['getQuote']
        );
        $this->model = new ReCaptchaSession(
            $this->timezone,
            $this->transparentSession,
            $this->checkoutSession
        );
    }

    public function testSaveIfThereIsNoActiveQuote(): void
    {
        $this->checkoutSession->expects($this->once())
            ->method('getQuote')
            ->willReturn(null);
        $this->assertFalse($this->model->save());
    }

    public function testSaveIfThereIsActiveQuote(): void
    {
        $quote = $this->createMock(CartInterface::class);
        $quote->expects($this->once())
            ->method('getId')
            ->willReturn(1);
        $this->checkoutSession->expects($this->exactly(2))
            ->method('getQuote')
            ->willReturn($quote);
        $this->timezone->expects($this->once())
            ->method('date')
            ->willReturn(new \Datetime('@1670607221'));
        $this->transparentSession->expects($this->once())
            ->method('setData')
            ->with('paypal_payflowpro_recaptcha', ['quote_id' => 1, 'verified_at' => 1670607221]);
        $this->assertTrue($this->model->save());
    }

    public function testIsInvalidIfQuoteIdIsMissing(): void
    {
        $this->transparentSession->expects($this->once())
            ->method('getData')
            ->with('paypal_payflowpro_recaptcha')
            ->willReturn(null);
        $this->assertFalse($this->model->isValid(1));
    }

    public function testIsInvalidIfQuoteIdDoesNotMatch(): void
    {
        $this->transparentSession->expects($this->once())
            ->method('getData')
            ->with('paypal_payflowpro_recaptcha')
            ->willReturn(['quote_id' => 2, 'verified_at' => 1670607221]);
        $this->assertFalse($this->model->isValid(1));
    }

    public function testIsInvalidIfExpired(): void
    {
        $this->timezone->expects($this->once())
            ->method('date')
            ->willReturn(new \Datetime('@1670607342'));
        $this->transparentSession->expects($this->once())
            ->method('getData')
            ->with('paypal_payflowpro_recaptcha')
            ->willReturn(['quote_id' => 1, 'verified_at' => 1670607221]);
        $this->assertFalse($this->model->isValid(1));
    }

    public function testIsInvalidIfNotExpired(): void
    {
        $this->timezone->expects($this->once())
            ->method('date')
            ->willReturn(new \Datetime('@1670607340'));
        $this->transparentSession->expects($this->once())
            ->method('getData')
            ->with('paypal_payflowpro_recaptcha')
            ->willReturn(['quote_id' => 1, 'verified_at' => 1670607221]);
        $this->transparentSession->expects($this->once())
            ->method('unsetData')
            ->with('paypal_payflowpro_recaptcha');
        $this->assertTrue($this->model->isValid(1));
    }
}
