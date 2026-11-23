<?php

declare(strict_types=1);

namespace Myca\Rma\Test\Unit\Model;

use Myca\Rma\Model\ReturnPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReturnPolicyTest extends TestCase
{
    private ReturnPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ReturnPolicy();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refundPartyProvider(): array
    {
        return [
            'achat MYCA en ligne : MYCA rembourse' => [ReturnPolicy::ORIGIN_ONLINE, ReturnPolicy::PARTY_MYCA],
            'dropshipping : la boutique rembourse' => [ReturnPolicy::ORIGIN_DROPSHIP, ReturnPolicy::PARTY_PARTNER],
            'boutique physique : aucun remboursement MYCA' => [ReturnPolicy::ORIGIN_STORE, ReturnPolicy::PARTY_NONE],
        ];
    }

    #[DataProvider('refundPartyProvider')]
    public function testRefundPartyDependsOnOrigin(string $origin, string $party): void
    {
        $this->assertSame($party, $this->policy->getRefundParty($origin));
    }

    public function testUnknownOriginIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->policy->getRefundParty('caisse');
    }

    public function testMycaCreditByOriginAndType(): void
    {
        $this->assertTrue($this->policy->issuesMycaCredit(ReturnPolicy::ORIGIN_ONLINE, ReturnPolicy::TYPE_RETURN));
        $this->assertTrue($this->policy->issuesMycaCredit(ReturnPolicy::ORIGIN_DROPSHIP, ReturnPolicy::TYPE_RETURN), 'avoir partenaire');
        $this->assertFalse($this->policy->issuesMycaCredit(ReturnPolicy::ORIGIN_STORE, ReturnPolicy::TYPE_RETURN));
        $this->assertTrue($this->policy->issuesMycaCredit(ReturnPolicy::ORIGIN_STORE, ReturnPolicy::TYPE_WARRANTY), 'garantie fournisseur');
        $this->assertFalse($this->policy->issuesMycaCredit(ReturnPolicy::ORIGIN_ONLINE, ReturnPolicy::TYPE_EXCHANGE));
    }

    public function testStoreSalesOnlyAllowWarrantyClaims(): void
    {
        $this->assertSame([ReturnPolicy::TYPE_WARRANTY], $this->policy->getAllowedTypes(ReturnPolicy::ORIGIN_STORE));
        $this->assertContains(ReturnPolicy::TYPE_RETURN, $this->policy->getAllowedTypes(ReturnPolicy::ORIGIN_DROPSHIP));
    }

    public function testThirtyDaysOnlyWhenEnabled(): void
    {
        $this->assertSame(14, $this->policy->getPolicyDays(ReturnPolicy::TYPE_RETURN, false, 730));
        $this->assertSame(30, $this->policy->getPolicyDays(ReturnPolicy::TYPE_EXCHANGE, true, 730));
        $this->assertSame(730, $this->policy->getPolicyDays(ReturnPolicy::TYPE_WARRANTY, false, 730));
    }

    public function testDeadlineCountsFromDelivery(): void
    {
        $delivered = new \DateTimeImmutable('2026-11-02 10:00:00');
        $this->assertTrue($this->policy->isWithinDeadline($delivered, 14, new \DateTimeImmutable('2026-11-16 23:00:00')));
        $this->assertFalse($this->policy->isWithinDeadline($delivered, 14, new \DateTimeImmutable('2026-11-17 00:00:01')));
        $this->assertTrue($this->policy->isWithinDeadline(null, 14, new \DateTimeImmutable('2030-01-01')), 'non livrée : délai non commencé');
    }

    public function testShippingIsRefundedOnlyOnFullWithdrawal(): void
    {
        $refundable = [10 => 2.0, 11 => 1.0];
        $this->assertTrue($this->policy->isFullWithdrawal(ReturnPolicy::TYPE_RETURN, [10 => 2.0, 11 => 1.0], $refundable));
        $this->assertFalse($this->policy->isFullWithdrawal(ReturnPolicy::TYPE_RETURN, [10 => 1.0, 11 => 1.0], $refundable), 'retour partiel');
        $this->assertFalse($this->policy->isFullWithdrawal(ReturnPolicy::TYPE_WARRANTY, [10 => 2.0, 11 => 1.0], $refundable));
        $this->assertTrue($this->policy->refundsShipping(ReturnPolicy::ORIGIN_ONLINE, ReturnPolicy::TYPE_RETURN, $refundable, $refundable));
        $this->assertFalse($this->policy->refundsShipping(ReturnPolicy::ORIGIN_DROPSHIP, ReturnPolicy::TYPE_RETURN, $refundable, $refundable), 'avoir boutique : selon contrat');
    }
}
