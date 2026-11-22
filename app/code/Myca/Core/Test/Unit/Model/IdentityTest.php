<?php

declare(strict_types=1);

namespace Myca\Core\Test\Unit\Model;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Myca\Core\Model\Identity;
use PHPUnit\Framework\TestCase;

class IdentityTest extends TestCase
{
    public function testGuestIsPublic(): void
    {
        $this->assertSame(Identity::PUBLIC, $this->createIdentity(false, '0', '2,4')->get());
    }

    public function testCustomerOutsidePartnerGroupsIsClient(): void
    {
        $this->assertSame(Identity::CLIENT, $this->createIdentity(true, '1', '2,4')->get());
    }

    public function testCustomerInPartnerGroupIsPartner(): void
    {
        $this->assertSame(Identity::PARTNER, $this->createIdentity(true, '4', '2,4')->get());
    }

    public function testNoPartnerGroupConfiguredMeansClient(): void
    {
        $this->assertSame(Identity::CLIENT, $this->createIdentity(true, '4', null)->get());
    }

    private function createIdentity(bool $loggedIn, string $groupId, ?string $partnerGroups): Identity
    {
        $httpContext = $this->createStub(HttpContext::class);
        $httpContext->method('getValue')->willReturnMap([
            [CustomerContext::CONTEXT_AUTH, $loggedIn],
            [CustomerContext::CONTEXT_GROUP, $groupId],
        ]);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($partnerGroups);

        return new Identity($httpContext, $scopeConfig);
    }
}
