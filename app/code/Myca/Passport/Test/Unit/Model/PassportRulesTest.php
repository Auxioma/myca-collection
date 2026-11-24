<?php

declare(strict_types=1);

namespace Myca\Passport\Test\Unit\Model;

use Myca\Passport\Model\LotFields;
use Myca\Passport\Model\PassportToken;
use PHPUnit\Framework\TestCase;

class PassportRulesTest extends TestCase
{
    public function testTokensAreRandomReadableAndUnique(): void
    {
        $generator = new PassportToken();
        $tokens = [];
        for ($i = 0; $i < 2000; $i++) {
            $token = $generator->generate();
            $this->assertTrue($generator->isValid($token));
            $this->assertDoesNotMatchRegularExpression('/[ILOU]/', $token, 'pas de caractère ambigu sur une étiquette');
            $tokens[$token] = true;
        }
        $this->assertCount(2000, $tokens);
    }

    public function testCodeTypedOrScannedIsNormalized(): void
    {
        $generator = new PassportToken();
        $token = '0123456789ABCDEFGHJK';
        $this->assertSame('MYCA-0123-4567-89AB-CDEF-GHJK', $generator->format($token));
        $this->assertSame($token, $generator->normalize('myca-0123-4567-89ab-cdef-ghjk'));
        $this->assertSame($token, $generator->normalize('https://www.myca.fr/passeport/' . $token));
        $this->assertSame('0123456789ABCDEFGHJK', $generator->normalize('O123456789ABCDEFGHJK'), 'O lu 0');
        $this->assertFalse($generator->isValid($generator->normalize('ABC')));
    }

    public function testPendingDataIsNeverPublished(): void
    {
        $fields = new LotFields();
        $statuses = ['origin' => LotFields::PENDING, 'material' => LotFields::DECLARED];
        $this->assertNull($fields->getPublicValue($statuses, 'origin', 'Portugal'));
        $this->assertSame('Piqué de coton', $fields->getPublicValue($statuses, 'material', 'Piqué de coton'));
        $this->assertNull($fields->getPublicValue([], 'cut', 'Classique'), 'sans statut : en cours de validation');
    }

    public function testCertificateRequiresCertifiedIdentity(): void
    {
        $fields = new LotFields();
        $lot = ['collection' => 'España', 'model_reference' => 'ESP-HID'];
        $certified = ['collection' => LotFields::CERTIFIED, 'model_reference' => LotFields::CERTIFIED];
        $this->assertTrue($fields->canCertify($lot, $certified));
        $this->assertFalse($fields->canCertify($lot, ['collection' => LotFields::CERTIFIED, 'model_reference' => LotFields::DECLARED]));
        $this->assertFalse($fields->canCertify(['collection' => 'España', 'model_reference' => ''], $certified));
    }
}
