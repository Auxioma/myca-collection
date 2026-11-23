<?php

declare(strict_types=1);

namespace Myca\Loyalty\Test\Unit\Model;

use Myca\Loyalty\Model\PointsCalculator;
use PHPUnit\Framework\TestCase;

class PointsCalculatorTest extends TestCase
{
    private PointsCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PointsCalculator();
    }

    /**
     * Exemple 1 du cahier des charges : polo 70 € + tee-shirt 40 € = 110 points ; retour du tee-shirt = −40.
     */
    public function testPartialReturnRemovesOnlyTheReturnedLine(): void
    {
        $polo = $this->calculator->pointsForLine(70.0, 0.0, 1.0);
        $teeShirt = $this->calculator->pointsForLine(40.0, 0.0, 1.0);
        $this->assertSame(110, $polo + $teeShirt);
        $this->assertSame(40, $this->calculator->pointsToRemove($teeShirt, 1.0, 1.0, 0));
        $this->assertSame(70, $polo + $teeShirt - 40);
    }

    /**
     * Exemple 3 : 105 € de produits − coupon 10 € = 95 points ; la livraison n'est jamais comptée.
     */
    public function testPointsAreComputedAfterDiscount(): void
    {
        $this->assertSame(95, $this->calculator->pointsForLine(105.0, -10.0, 1.0));
        $this->assertSame(0, $this->calculator->pointsForLine(10.0, 15.0, 1.0), 'jamais négatif');
        $this->assertSame(69, $this->calculator->pointsForLine(69.99, 0.0, 1.0), 'arrondi inférieur');
    }

    public function testOneItemOutOfThreeAndRepeatedRefund(): void
    {
        $earned = $this->calculator->pointsForLine(150.0, 0.0, 1.0);
        $first = $this->calculator->pointsToRemove($earned, 3.0, 1.0, 0);
        $this->assertSame(50, $first);
        $this->assertSame(0, $this->calculator->pointsToRemove($earned, 3.0, 1.0, $first), 'même remboursement rejoué');
    }

    public function testRemovalTakesPendingPointsFirst(): void
    {
        $this->assertSame(['pending' => 30, 'available' => 10], $this->calculator->splitRemoval(40, 30));
        $this->assertSame(['pending' => 0, 'available' => 40], $this->calculator->splitRemoval(40, 0), 'points déjà validés');
    }

    /**
     * Invariants sur des remboursements partiels aléatoires : on ne retire jamais plus que le gain de la ligne,
     * et un retour total retire exactement le gain.
     */
    public function testRandomPartialRefundsNeverRemoveMoreThanEarned(): void
    {
        // Générateur pseudo-aléatoire à graine fixe : le test reste reproductible.
        $seed = 20261123;
        $random = static function (int $min, int $max) use (&$seed): int {
            $seed = ($seed * 1103515245 + 12345) % 2147483648;

            return $min + $seed % ($max - $min + 1);
        };
        for ($run = 0; $run < 500; $run++) {
            $qty = $random(1, 7);
            $earned = $this->calculator->pointsForLine($random(100, 50000) / 100 * $qty, -$random(0, 500) / 100, $random(1, 30) / 10);
            $removed = 0;
            $refunded = 0;
            while ($refunded < $qty) {
                $refunded += $random(1, $qty - $refunded);
                $delta = $this->calculator->pointsToRemove($earned, (float) $qty, (float) $refunded, $removed);
                $this->assertGreaterThanOrEqual(0, $delta);
                $removed += $delta;
                $this->assertLessThanOrEqual($earned, $removed);
            }
            $this->assertSame($earned, $removed, 'retour total : tout le gain est retiré');
        }
    }

    public function testRewardsParsingAndNextReward(): void
    {
        $rewards = PointsCalculator::parseRewards('150=10, 100=6,200=15.50');
        $this->assertSame([100 => 6.0, 150 => 10.0, 200 => 15.5], $rewards);
        $this->assertSame(['points' => 150, 'amount' => 10.0], $this->calculator->nextReward($rewards, 125));
        $this->assertNull($this->calculator->nextReward($rewards, 200));
        $this->assertSame([], PointsCalculator::parseRewards('100=six'));
        $this->assertSame([], PointsCalculator::parseRewards('0=5'));
    }
}
