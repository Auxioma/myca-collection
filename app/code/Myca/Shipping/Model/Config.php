<?php

declare(strict_types=1);

namespace Myca\Shipping\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * Paramètres MYCA de livraison (Magasins > Configuration > MYCA > Livraison).
 */
class Config
{
    private const PATH_THRESHOLD = 'myca_shipping/relay/free_threshold';
    private const PATH_RELAY_CARRIERS = 'myca_shipping/relay/carriers';
    private const PATH_DESTINATIONS = 'myca_shipping/destinations/list';
    private const PATH_HOME_RATES = 'carriers/mycahome/rates';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    public function getFreeRelayThreshold(?int $storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(self::PATH_THRESHOLD, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Codes des transporteurs « point relais » (modules officiels Mondial Relay, Colissimo…).
     *
     * @return string[]
     */
    public function getRelayCarrierCodes(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(self::PATH_RELAY_CARRIERS, ScopeInterface::SCOPE_STORE, $storeId);

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @return array<string, array{relay: bool, home: bool}> code pays => modes desservis
     */
    public function getDestinations(?int $storeId = null): array
    {
        $destinations = [];
        foreach ($this->readRows(self::PATH_DESTINATIONS, $storeId) as $row) {
            $country = strtoupper(trim((string) ($row['country_id'] ?? '')));
            if ($country !== '') {
                $destinations[$country] = [
                    'relay' => (bool) ($row['relay'] ?? false),
                    'home' => (bool) ($row['home'] ?? false),
                ];
            }
        }

        return $destinations;
    }

    /**
     * @return array<int, array{country_id: string, weight_from: float, weight_to: float, price: float}>
     */
    public function getHomeRates(?int $storeId = null): array
    {
        $rates = [];
        foreach ($this->readRows(self::PATH_HOME_RATES, $storeId) as $row) {
            $rates[] = [
                'country_id' => strtoupper(trim((string) ($row['country_id'] ?? ''))),
                'weight_from' => (float) ($row['weight_from'] ?? 0),
                'weight_to' => (float) ($row['weight_to'] ?? 0),
                'price' => (float) ($row['price'] ?? 0),
            ];
        }

        return $rates;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function readRows(string $path, ?int $storeId): array
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        if (is_array($value)) {
            return $value;
        }
        $rows = $value ? $this->json->unserialize((string) $value) : [];

        return is_array($rows) ? $rows : [];
    }
}
