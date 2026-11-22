<?php

declare(strict_types=1);

namespace Myca\Core\Model;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Model\ScopeInterface;

/**
 * Identité visuelle du visiteur (public, client ou partenaire), déduite du groupe client.
 *
 * Lit le contexte HTTP plutôt que la session : ce contexte fait partie de la clé du cache de page,
 * donc chaque identité obtient sa propre version en cache.
 */
class Identity
{
    public const PUBLIC = 'public';
    public const CLIENT = 'client';
    public const PARTNER = 'partner';

    private const XML_PATH_PARTNER_GROUPS = 'myca_core/identity/partner_groups';

    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function get(): string
    {
        if (!$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH)) {
            return self::PUBLIC;
        }

        $groupId = (string) $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        return in_array($groupId, $this->getPartnerGroupIds(), true) ? self::PARTNER : self::CLIENT;
    }

    /**
     * @return string[]
     */
    private function getPartnerGroupIds(): array
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_PARTNER_GROUPS, ScopeInterface::SCOPE_WEBSITE);

        return $value === '' ? [] : array_map('trim', explode(',', $value));
    }
}
