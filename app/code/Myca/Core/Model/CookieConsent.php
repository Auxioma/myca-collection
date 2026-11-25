<?php

declare(strict_types=1);

namespace Myca\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

/**
 * Consentement aux cookies : catégories, version de la politique, durée de validité du choix, et preuve
 * enregistrée côté serveur (identifiant aléatoire du cookie, choix, date ; ni IP ni navigateur).
 */
class CookieConsent
{
    public const COOKIE_NAME = 'myca_consent';
    public const CATEGORIES = ['analytics', 'marketing'];
    public const ACTIONS = ['accept_all', 'reject_all', 'custom'];

    private const XML_VERSION = 'myca_site/cookies/policy_version';
    private const XML_LIFETIME = 'myca_site/cookies/lifetime_days';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getPolicyVersion(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_VERSION, ScopeInterface::SCOPE_STORE) ?: '1');
    }

    /**
     * Durée de validité du choix (6 mois recommandés par la CNIL, 13 mois au plus).
     */
    public function getLifetimeDays(): int
    {
        return min(395, max(1, (int) ($this->scopeConfig->getValue(self::XML_LIFETIME, ScopeInterface::SCOPE_STORE) ?: 180)));
    }

    /**
     * Contrôle et normalise un choix reçu du navigateur.
     *
     * @param array<string, mixed> $input
     * @return array{consent_id: string, action: string, analytics: int, marketing: int}
     * @throws LocalizedException
     */
    public function normalize(array $input): array
    {
        $consentId = (string) ($input['consent_id'] ?? '');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $consentId)) {
            throw new LocalizedException(__('Identifiant de consentement invalide.'));
        }
        $action = (string) ($input['action'] ?? '');
        if (!in_array($action, self::ACTIONS, true)) {
            throw new LocalizedException(__('Choix de cookies invalide.'));
        }
        $choose = static fn (string $category): int => match ($action) {
            'accept_all' => 1,
            'reject_all' => 0,
            default => !empty($input[$category]) && $input[$category] !== 'false' ? 1 : 0,
        };

        return [
            'consent_id' => $consentId,
            'action' => $action,
            'analytics' => $choose('analytics'),
            'marketing' => $choose('marketing'),
        ];
    }

    /**
     * @param array{consent_id: string, action: string, analytics: int, marketing: int} $choice
     */
    public function record(array $choice, int $storeId): void
    {
        $this->resource->getConnection()->insert($this->resource->getTableName('myca_cookie_consent'), $choice + [
            'store_id' => $storeId,
            'policy_version' => $this->getPolicyVersion(),
        ]);
    }
}
