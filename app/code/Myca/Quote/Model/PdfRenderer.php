<?php

declare(strict_types=1);

namespace Myca\Quote\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

/**
 * PDF d'une proposition de devis : numéro et version, dates, client, lignes, totaux HT / TVA / TTC, validité et
 * mention « ceci n'est pas une facture ». Polices embarquées de Magento (accents et symbole euro).
 */
class PdfRenderer
{
    private const MARGIN = 50;
    private const PAGE_BOTTOM = 80;

    private \Zend_Pdf_Resource_Font $regular;
    private \Zend_Pdf_Resource_Font $bold;

    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * @param array{increment_id: string, version: int, date: string, valid_until: string, customer: string,
     *     email: string, note: string, totals: array{subtotal_excl_tax: float, tax_amount: float, grand_total: float}} $quote
     * @param array<int, array{name: string, sku: string, qty: float, unit_price_incl_tax: float, tax_percent: float}> $lines
     */
    public function render(array $quote, array $lines): string
    {
        $root = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
        $this->regular = \Zend_Pdf_Font::fontWithPath($root->getAbsolutePath('lib/internal/GnuFreeFont/FreeSerif.ttf'));
        $this->bold = \Zend_Pdf_Font::fontWithPath($root->getAbsolutePath('lib/internal/GnuFreeFont/FreeSerifBold.ttf'));

        $pdf = new \Zend_Pdf();
        $page = $this->newPage($pdf);
        $y = 790;
        $this->text($page, 'MYCA®', self::MARGIN, $y, 22, true);
        $this->text($page, 'L’élégance sportive', self::MARGIN, $y - 16, 10);
        $this->text($page, (string) __('DEVIS %1', $quote['increment_id']), 330, $y, 16, true);
        $this->text($page, (string) __('Version %1 du %2', $quote['version'], $quote['date']), 330, $y - 18, 10);
        $this->text($page, (string) __('Valable jusqu’au %1 inclus', $quote['valid_until']), 330, $y - 32, 10, true);

        $y -= 80;
        $this->text($page, (string) __('Client'), self::MARGIN, $y, 11, true);
        $this->text($page, $quote['customer'], self::MARGIN, $y - 15, 10);
        $this->text($page, $quote['email'], self::MARGIN, $y - 29, 10);

        $y -= 65;
        $columns = [self::MARGIN, 300, 345, 425, 470];
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.92));
        $page->drawRectangle(self::MARGIN - 5, $y - 6, 550, $y + 14, \Zend_Pdf_Page::SHAPE_DRAW_FILL);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        foreach ([__('Article'), __('Qté'), __('PU TTC'), __('TVA'), __('Total TTC')] as $index => $label) {
            $this->text($page, (string) $label, $columns[$index], $y, 10, true);
        }
        $y -= 22;
        foreach ($lines as $line) {
            if ($y < self::PAGE_BOTTOM) {
                $page = $this->newPage($pdf);
                $y = 790;
            }
            $this->text($page, mb_strimwidth($line['name'], 0, 48, '…'), $columns[0], $y, 10);
            $this->text($page, (string) __('Réf. %1', $line['sku']), $columns[0], $y - 12, 8);
            $this->text($page, $this->number($line['qty'], 0), $columns[1], $y, 10);
            $this->text($page, $this->money($line['unit_price_incl_tax']), $columns[2], $y, 10);
            $this->text($page, $this->number($line['tax_percent'], 1) . ' %', $columns[3], $y, 10);
            $this->text($page, $this->money($line['unit_price_incl_tax'] * $line['qty']), $columns[4], $y, 10);
            $y -= 30;
        }

        $y -= 10;
        foreach ([
            [__('Total HT'), $quote['totals']['subtotal_excl_tax'], false],
            [__('TVA'), $quote['totals']['tax_amount'], false],
            [__('Total TTC'), $quote['totals']['grand_total'], true],
        ] as [$label, $amount, $strong]) {
            $this->text($page, (string) $label, 345, $y, 11, $strong);
            $this->text($page, $this->money($amount), 470, $y, 11, $strong);
            $y -= 18;
        }

        if ($quote['note'] !== '') {
            $y -= 15;
            $this->text($page, (string) __('Conditions'), self::MARGIN, $y, 11, true);
            foreach (explode("\n", wordwrap($quote['note'], 95, "\n", true)) as $row) {
                $y -= 14;
                $this->text($page, $row, self::MARGIN, $y, 10);
            }
        }

        $this->text($page, (string) __('Ce devis n’est pas une facture. Aucune somme n’est due avant la commande et son paiement.'), self::MARGIN, 60, 9);
        $this->text($page, (string) __('Livraison et éventuels frais calculés à la commande, selon les conditions générales de vente.'), self::MARGIN, 47, 9);

        return $pdf->render();
    }

    private function newPage(\Zend_Pdf $pdf): \Zend_Pdf_Page
    {
        $page = $pdf->newPage(\Zend_Pdf_Page::SIZE_A4);
        $pdf->pages[] = $page;

        return $page;
    }

    private function text(\Zend_Pdf_Page $page, string $text, float $x, float $y, float $size, bool $bold = false): void
    {
        $page->setFont($bold ? $this->bold : $this->regular, $size);
        $page->drawText($text, $x, $y, 'UTF-8');
    }

    private function money(float $amount): string
    {
        return $this->number($amount, 2) . "\u{00A0}€";
    }

    private function number(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', "\u{00A0}");
    }
}
