<?php

declare(strict_types=1);

namespace Myca\Loyalty\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Magasins › Configuration › MYCA › Programme fidélité.
 */
class Config
{
    private const PATH = 'myca_loyalty/program/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enabled', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    public function getPointsPerEuro(?int $websiteId = null): float
    {
        return max(0.0, (float) $this->scopeConfig->getValue(self::PATH . 'points_per_euro', ScopeInterface::SCOPE_WEBSITE, $websiteId));
    }

    /**
     * @return array<int, float>
     */
    public function getRewards(?int $websiteId = null): array
    {
        return PointsCalculator::parseRewards((string) $this->scopeConfig->getValue(self::PATH . 'rewards', ScopeInterface::SCOPE_WEBSITE, $websiteId));
    }

    public function getExpiryMonths(?int $websiteId = null): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::PATH . 'expiry_months', ScopeInterface::SCOPE_WEBSITE, $websiteId));
    }

    public function isB2bEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'b2b_enabled', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }
}
