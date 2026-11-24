<?php

declare(strict_types=1);

namespace Myca\Partner\Test\Unit\Model;

use Myca\Partner\Model\PartnerValidator;
use PHPUnit\Framework\TestCase;

class PartnerValidatorTest extends TestCase
{
    private PartnerValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PartnerValidator();
    }

    public function testSiretLuhnKey(): void
    {
        $this->assertSame('73282932000074', $this->validator->normalizeSiret('732 829 320 00074'));
        $this->assertNull($this->validator->normalizeSiret('73282932000075'), 'clé fausse');
        $this->assertNull($this->validator->normalizeSiret('123'), 'longueur');
        $this->assertSame('35600000000051', $this->validator->normalizeSiret('35600000000051'), 'exception La Poste');
    }

    public function testFrenchVatKeyMatchesSiren(): void
    {
        $this->assertSame('FR44732829320', $this->validator->normalizeVat('fr 44 732 829 320', '73282932000074'));
        $this->assertNull($this->validator->normalizeVat('FR45732829320'), 'clé fausse');
        $this->assertNull($this->validator->normalizeVat('FR44732829320', '55210055400013'), 'SIREN différent du SIRET');
        $this->assertSame('DE123456789', $this->validator->normalizeVat('DE 123456789'));
        $this->assertNull($this->validator->normalizeVat('US123456789'));
    }

    public function testSalesModesAndContact(): void
    {
        $this->assertSame(['dropship', 'stock'], $this->validator->parseSalesModes('both'));
        $this->assertNull($this->validator->parseSalesModes('autre'));
        $this->assertSame(['Jean-Marc', 'Dupont'], $this->validator->splitName('Jean-Marc Dupont'));
        $this->assertSame(['Anne Marie', 'Martin'], $this->validator->splitName(' Anne  Marie Martin '));
        $this->assertNull($this->validator->normalizePhone('abc'));
        $this->assertSame('+33 6 12 34 56 78', $this->validator->normalizePhone('+33 6 12 34 56 78'));
    }
}
