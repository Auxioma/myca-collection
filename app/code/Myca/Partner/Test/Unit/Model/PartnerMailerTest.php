<?php

declare(strict_types=1);

namespace Myca\Partner\Test\Unit\Model;

use Myca\Partner\Model\PartnerMailer;
use PHPUnit\Framework\TestCase;

class PartnerMailerTest extends TestCase
{
    public function testParseRecipientsKeepsValidUniqueAddresses(): void
    {
        $this->assertSame(
            ['partenaires@myca.fr', 'direction@myca.fr'],
            PartnerMailer::parseRecipients(' partenaires@myca.fr, direction@myca.fr;partenaires@myca.fr  pas-une-adresse ')
        );
    }

    public function testParseRecipientsEmpty(): void
    {
        $this->assertSame([], PartnerMailer::parseRecipients(''));
    }
}
