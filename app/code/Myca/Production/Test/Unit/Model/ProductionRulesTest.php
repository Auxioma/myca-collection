<?php

declare(strict_types=1);

namespace Myca\Production\Test\Unit\Model;

use Myca\Production\Model\ProductionRules;
use PHPUnit\Framework\TestCase;

class ProductionRulesTest extends TestCase
{
    private ProductionRules $rules;

    protected function setUp(): void
    {
        $this->rules = new ProductionRules();
    }

    public function testMoqIsCheckedPerColor(): void
    {
        $lines = [
            ['color' => 'Blanc', 'qty_planned' => 60], ['color' => 'Blanc', 'qty_planned' => 60],
            ['color' => 'Marine', 'qty_planned' => 40],
            ['color' => 'Rouge', 'qty_planned' => 0],
        ];
        $this->assertSame(['Marine' => 40], $this->rules->getMoqShortfalls($lines, 100), 'couleur non commandée ignorée');
        $this->assertSame([], $this->rules->getMoqShortfalls($lines, 0));
    }

    public function testCommitRequiresQuantitiesPriceToleranceAndMoq(): void
    {
        $order = ['tolerances_validated_at' => null, 'moq_per_color' => 100, 'unit_factory_price' => 0];
        // Quantité nulle, tolérances non validées, prix absent ; une couleur à 0 pièce n'est pas contrôlée au MOQ.
        $this->assertCount(3, $this->rules->getCommitBlockers($order, [['color' => 'Blanc', 'qty_planned' => 0]]));
        $ready = ['tolerances_validated_at' => '2026-11-24 10:00:00', 'moq_per_color' => 100, 'unit_factory_price' => 16.0];
        $this->assertSame([], $this->rules->getCommitBlockers($ready, [['color' => 'Blanc', 'qty_planned' => 120]]));
    }

    public function testStatusFlowFreezesQuantitiesAfterCommitment(): void
    {
        $this->assertTrue($this->rules->canTransition(ProductionRules::PLANNED, ProductionRules::COMMITTED));
        $this->assertFalse($this->rules->canTransition(ProductionRules::PLANNED, ProductionRules::RECEIVED));
        $this->assertFalse($this->rules->canTransition(ProductionRules::IN_PRODUCTION, ProductionRules::CANCELED), 'production lancée');
        $this->assertFalse($this->rules->isEditable(ProductionRules::COMMITTED));
        $this->assertFalse($this->rules->canReceive(ProductionRules::COMMITTED), 'pas de réception avant le lancement');
    }

    public function testQualityDecisionFollowsChecklistAndValidatedLimits(): void
    {
        $allOk = array_fill_keys(ProductionRules::CHECKLIST, 'ok');
        $limits = ['critical' => 0, 'major' => 2, 'minor' => 5];
        $this->assertContains(ProductionRules::ACCEPTED, $this->rules->getAllowedDecisions($allOk, ['critical' => 0, 'major' => 2, 'minor' => 5], $limits));
        $this->assertNotContains(ProductionRules::ACCEPTED, $this->rules->getAllowedDecisions($allOk, ['critical' => 0, 'major' => 3, 'minor' => 0], $limits));
        $this->assertSame([ProductionRules::REFUSED], $this->rules->getAllowedDecisions($allOk, ['critical' => 1, 'major' => 0, 'minor' => 0], $limits));
        $this->assertNotContains(ProductionRules::ACCEPTED, $this->rules->getAllowedDecisions(['color' => 'nok'] + $allOk, ['critical' => 0, 'major' => 0, 'minor' => 0], $limits));
    }

    public function testLandedCostSeparatesFactoryPriceAndFees(): void
    {
        $this->assertSame(17.5, $this->rules->getLandedUnitCost(16.0, 1200.0, 800));
        $this->assertNull($this->rules->getLandedUnitCost(16.0, 1200.0, 0));
    }

    public function testTechpackInputs(): void
    {
        $this->assertSame(['Coton' => 95.0, 'Élasthanne' => 5.0], $this->rules->parseComposition('Coton=95, Élasthanne=5'));
        $this->assertNull($this->rules->parseComposition('Coton=90'), 'total différent de 100 %');
        $this->assertSame(['S' => [96.0, 68.0, 42.0], 'M' => [100.0, 70.0, 44.0]], $this->rules->parseSizeChart("S; 96; 68; 42\nM;100;70;44"));
        $this->assertNull($this->rules->parseSizeChart('S; 96; abc'));
    }
}
