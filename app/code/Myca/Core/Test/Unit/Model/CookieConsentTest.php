<?php

declare(strict_types=1);

namespace Myca\Core\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Myca\Core\Model\CookieConsent;
use PHPUnit\Framework\TestCase;

class CookieConsentTest extends TestCase
{
    private const ID = '0f8b2c4e-1a2b-4c3d-8e9f-0123456789ab';

    private CookieConsent $consent;

    protected function setUp(): void
    {
        $this->consent = new CookieConsent(
            $this->createStub(ResourceConnection::class),
            $this->createStub(ScopeConfigInterface::class)
        );
    }

    public function testRejectAllIgnoresCheckedCategories(): void
    {
        $this->assertSame(
            ['consent_id' => self::ID, 'action' => 'reject_all', 'analytics' => 0, 'marketing' => 0],
            $this->consent->normalize(['consent_id' => self::ID, 'action' => 'reject_all', 'analytics' => '1'])
        );
    }

    public function testAcceptAllAndCustom(): void
    {
        $this->assertSame(1, $this->consent->normalize(['consent_id' => self::ID, 'action' => 'accept_all'])['marketing']);
        $custom = $this->consent->normalize(['consent_id' => self::ID, 'action' => 'custom', 'analytics' => '1', 'marketing' => '0']);
        $this->assertSame([1, 0], [$custom['analytics'], $custom['marketing']]);
    }

    public function testRejectsForgedIdentifierAndAction(): void
    {
        $this->expectException(LocalizedException::class);
        $this->consent->normalize(['consent_id' => "x' OR 1=1", 'action' => 'accept_all']);
    }

    public function testRejectsUnknownAction(): void
    {
        $this->expectException(LocalizedException::class);
        $this->consent->normalize(['consent_id' => self::ID, 'action' => 'accept_some']);
    }

    public function testDefaultsWhenNotConfigured(): void
    {
        $this->assertSame('1', $this->consent->getPolicyVersion());
        $this->assertSame(180, $this->consent->getLifetimeDays());
    }
}
