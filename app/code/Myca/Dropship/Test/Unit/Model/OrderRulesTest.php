<?php

declare(strict_types=1);

namespace Myca\Dropship\Test\Unit\Model;

use Myca\Dropship\Model\OrderRules;
use PHPUnit\Framework\TestCase;

class OrderRulesTest extends TestCase
{
    private OrderRules $rules;

    protected function setUp(): void
    {
        $this->rules = new OrderRules();
    }

    public function testMinimumPerModelAndColor(): void
    {
        $lines = [
            '1|10' => ['label' => 'Polo Aurelio – Blanc', 'qty' => 100],
            '1|11' => ['label' => 'Polo Aurelio – Marine', 'qty' => 40],
        ];
        $this->assertSame(['Polo Aurelio – Marine'], $this->rules->getBelowMinimum($lines, 100));
        $this->assertSame([], $this->rules->getBelowMinimum($lines, 0));
    }

    public function testRecipientIsNormalizedAndValidated(): void
    {
        $result = $this->rules->normalizeRecipient([
            'firstname' => '  Léa ', 'lastname' => 'Martin', 'street' => "12  rue\n des Lilas", 'postcode' => '75011',
            'city' => 'Paris', 'country_id' => 'fr', 'telephone' => '06 12 34 56 78', 'email' => 'lea@example.com', 'iban' => 'FR76…',
        ], ['FR']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('12 rue des Lilas', $result['recipient']['street']);
        $this->assertSame('FR', $result['recipient']['country_id']);
        $this->assertArrayNotHasKey('iban', $result['recipient']);
        $this->assertArrayNotHasKey('email', $result['recipient']);
    }

    public function testRecipientErrors(): void
    {
        $result = $this->rules->normalizeRecipient([
            'firstname' => 'Léa', 'lastname' => '', 'street' => 'x', 'postcode' => '7501', 'city' => 'Paris',
            'country_id' => 'DE', 'telephone' => '12',
        ], ['FR']);
        $this->assertCount(3, $result['errors']);
    }
}
