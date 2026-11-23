<?php

declare(strict_types=1);

namespace Myca\Rma\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration des retours (Magasins › Configuration › MYCA › Retours & SAV).
 */
class Config
{
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Politique commerciale de 30 jours : désactivée tant qu'elle n'est pas validée (cahier des charges).
     */
    public function isCommercial30DaysEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag('myca_rma/policy/commercial_30_days', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getWarrantyDays(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue('myca_rma/policy/warranty_days', ScopeInterface::SCOPE_STORE, $storeId));
    }
}
