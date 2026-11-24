<?php

declare(strict_types=1);

namespace Myca\Passport\Model;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Magento\Framework\UrlInterface;

/**
 * QR code d'un passeport (SVG), pointant vers sa page publique /passeport/IDENTIFIANT. La bibliothèque est déjà
 * présente : c'est celle de l'authentification à deux facteurs de Magento.
 */
class QrCode
{
    public function __construct(private readonly UrlInterface $url)
    {
    }

    public function getPublicUrl(string $token): string
    {
        return $this->url->getUrl('', ['_direct' => 'passeport/' . $token, '_nosid' => true]);
    }

    public function getSvg(string $token, bool $withXmlDeclaration = false): string
    {
        $qrCode = new EndroidQrCode(
            data: $this->getPublicUrl($token),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 0
        );

        return (new SvgWriter())->write($qrCode, null, null, [
            SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => !$withXmlDeclaration,
        ])->getString();
    }
}
