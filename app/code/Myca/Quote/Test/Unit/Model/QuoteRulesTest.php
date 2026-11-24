<?php

declare(strict_types=1);

namespace Myca\Quote\Test\Unit\Model;

use Myca\Quote\Model\QuoteCalculator;
use Myca\Quote\Model\QuoteStatus;
use PHPUnit\Framework\TestCase;

class QuoteRulesTest extends TestCase
{
    public function testTotalsMatchTheCartFromIncludingTaxPrices(): void
    {
        $totals = (new QuoteCalculator())->totals([
            ['qty' => 50.0, 'unit_price_incl_tax' => 12.0, 'tax_percent' => 20.0],
            ['qty' => 50.0, 'unit_price_incl_tax' => 9.6, 'tax_percent' => 20.0],
        ]);
        $this->assertSame(['subtotal_excl_tax' => 900.0, 'tax_amount' => 180.0, 'grand_total' => 1080.0], $totals);
    }

    public function testValidityIncludesItsLastDay(): void
    {
        $calculator = new QuoteCalculator();
        $this->assertFalse($calculator->isExpired('2026-11-30', '2026-11-30'));
        $this->assertTrue($calculator->isExpired('2026-11-30', '2026-12-01'));
        $this->assertFalse($calculator->isExpired(null, '2030-01-01'), 'pas encore chiffré');
    }

    public function testCustomerAndMycaRights(): void
    {
        $status = new QuoteStatus();
        $this->assertTrue($status->canTransition(QuoteStatus::DRAFT, QuoteStatus::REQUESTED, QuoteStatus::ACTOR_CUSTOMER));
        $this->assertFalse($status->canTransition(QuoteStatus::REQUESTED, QuoteStatus::ACCEPTED, QuoteStatus::ACTOR_CUSTOMER), 'rien à accepter avant la proposition');
        $this->assertTrue($status->canTransition(QuoteStatus::PROPOSED, QuoteStatus::ACCEPTED, QuoteStatus::ACTOR_CUSTOMER));
        $this->assertFalse($status->canTransition(QuoteStatus::REQUESTED, QuoteStatus::PROPOSED, QuoteStatus::ACTOR_CUSTOMER), 'seul MYCA propose');
        $this->assertFalse($status->canTransition(QuoteStatus::ACCEPTED, QuoteStatus::PROPOSED, QuoteStatus::ACTOR_ADMIN), 'un devis accepté ne change plus');
        $this->assertFalse($status->canTransition(QuoteStatus::ORDERED, QuoteStatus::CANCELED, QuoteStatus::ACTOR_CUSTOMER));
    }
}
