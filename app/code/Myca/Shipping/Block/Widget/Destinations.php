<?php

declare(strict_types=1);

namespace Myca\Shipping\Block\Widget;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Myca\Shipping\Model\Config;

/**
 * Widget « MYCA – Destinations desservies » : la liste administrable des pays et modes de livraison (Magasins >
 * Configuration > MYCA > Livraison), affichée telle quelle sur la page Livraison. Aucun pays n'est annoncé s'il
 * n'est pas configuré.
 */
class Destinations extends Template implements BlockInterface
{
    /** @var string */
    protected $_template = 'Myca_Shipping::widget/destinations.phtml';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly Config $config,
        private readonly CountryInformationAcquirerInterface $countryInformation,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFreeRelayThreshold(): float
    {
        return $this->config->getFreeRelayThreshold();
    }

    /**
     * @return array<int, array{country: string, relay: bool, home: bool, home_from: ?float}> triées par pays
     */
    public function getRows(): array
    {
        $prices = [];
        foreach ($this->config->getHomeRates() as $rate) {
            $country = $rate['country_id'];
            $prices[$country] = min($prices[$country] ?? PHP_FLOAT_MAX, $rate['price']);
        }
        $rows = [];
        foreach ($this->config->getDestinations() as $code => $modes) {
            if (!$modes['relay'] && !$modes['home']) {
                continue;
            }
            $rows[] = [
                'country' => $this->countryName($code),
                'relay' => $modes['relay'],
                'home' => $modes['home'],
                'home_from' => $modes['home'] ? ($prices[$code] ?? null) : null,
            ];
        }
        usort($rows, static fn (array $a, array $b) => strcoll($a['country'], $b['country']));

        return $rows;
    }

    private function countryName(string $code): string
    {
        try {
            return (string) $this->countryInformation->getCountryInfo($code)->getFullNameLocale();
        } catch (\Exception) {
            return $code;
        }
    }
}
