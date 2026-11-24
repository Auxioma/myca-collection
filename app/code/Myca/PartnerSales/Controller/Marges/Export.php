<?php

declare(strict_types=1);

namespace Myca\PartnerSales\Controller\Marges;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Myca\Partner\Model\CurrentPartner;
use Myca\PartnerSales\Model\SalesData;

/**
 * « Exporter les ventes (CSV) » : lignes de la boutique, séparateur point-virgule (Excel français).
 */
class Export implements HttpGetActionInterface
{
    public function __construct(
        private readonly RawFactory $rawFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly CurrentPartner $currentPartner,
        private readonly SalesData $salesData
    ) {
    }

    public function execute(): ResultInterface
    {
        $partnerId = $this->currentPartner->getActiveId();
        if (!$this->customerSession->isLoggedIn() || $partnerId === null) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $sources = [
            SalesData::SOURCE_ORDER => 'déclaré (commande)',
            SalesData::SOURCE_PRICE_LIST => 'grille boutique',
            SalesData::SOURCE_PUBLIC => 'estimé (prix public)',
        ];
        $rows = [['Date', 'Commande', 'Modèle', 'Quantité', 'Achat MYCA HT (port inclus)', 'Vente HT', 'Origine du prix', 'Marge estimée HT']];
        foreach ($this->salesData->getLines($partnerId) as $line) {
            $rows[] = [
                date('d/m/Y', $line['time']),
                $line['order_increment_id'],
                $line['name'],
                $this->number($line['qty']),
                $this->number($line['cost']),
                $line['sale'] === null ? '' : $this->number($line['sale']),
                $line['source'] === null ? 'non déclaré' : $sources[$line['source']],
                $line['sale'] === null ? '' : $this->number($line['sale'] - $line['cost']),
            ];
        }
        $csv = "\u{FEFF}";
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(static fn ($cell) => '"' . str_replace('"', '""', (string) $cell) . '"', $row)) . "\r\n";
        }
        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/csv; charset=UTF-8', true)
            ->setHeader('Content-Disposition', 'attachment; filename="mes-ventes-myca.csv"', true)
            ->setHeader('Cache-Control', 'private, no-store', true)
            ->setContents($csv);

        return $result;
    }

    private function number(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
