<?php

declare(strict_types=1);

namespace Myca\Core\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Politique de rétention du journal d'audit : suppression quotidienne des entrées plus anciennes que la durée
 * configurée (Magasins > Configuration > MYCA > Site public > Journal d'audit). C'est la seule suppression prévue.
 */
class PurgeAuditLog
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function execute(): void
    {
        $days = max(30, (int) ($this->scopeConfig->getValue('myca_site/audit/retention_days') ?: 1095));
        $this->resource->getConnection()->delete(
            $this->resource->getTableName('myca_audit_log'),
            ['created_at < ?' => gmdate('Y-m-d H:i:s', time() - $days * 86400)]
        );
    }
}
