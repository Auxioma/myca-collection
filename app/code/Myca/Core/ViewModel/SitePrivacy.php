<?php

declare(strict_types=1);

namespace Myca\Core\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Myca\Core\Model\CookieConsent;

/**
 * Réglages du site public : bandeau de consentement, réseaux sociaux et moyens de paiement du pied de page
 * (Magasins > Configuration > MYCA > Site public).
 */
class SitePrivacy implements ArgumentInterface
{
    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];

    public function __construct(
        private readonly CookieConsent $cookieConsent,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return array{cookieName: string, version: string, lifetimeDays: int, saveUrl: string}
     */
    public function getConsentConfig(): array
    {
        return [
            'cookieName' => CookieConsent::COOKIE_NAME,
            'version' => $this->cookieConsent->getPolicyVersion(),
            'lifetimeDays' => $this->cookieConsent->getLifetimeDays(),
            'saveUrl' => $this->urlBuilder->getUrl('consentement/consent/save'),
        ];
    }

    public function getCookiePolicyUrl(): string
    {
        return $this->urlBuilder->getUrl(null, ['_direct' => 'cookies']);
    }

    /**
     * Adresse du réseau social, ou null s'il n'est pas renseigné (l'icône n'est alors pas un lien).
     */
    public function getSocialUrl(string $network): ?string
    {
        $url = trim((string) $this->scopeConfig->getValue('myca_site/footer/' . $network, ScopeInterface::SCOPE_STORE));

        return preg_match('#^https://#', $url) ? $url : null;
    }

    /**
     * Moyens de paiement réellement acceptés, tels que déclarés dans la configuration.
     *
     * @return string[]
     */
    public function getPaymentMethods(): array
    {
        $value = (string) $this->scopeConfig->getValue('myca_site/footer/payment_methods', ScopeInterface::SCOPE_STORE);

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
