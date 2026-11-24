<?php

declare(strict_types=1);

namespace Myca\Partner\ViewModel;

use Magento\Directory\Model\AllowedCountries;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Partner\Model\Partner;
use Myca\Partner\Model\PartnerManagement;
use Myca\Partner\Model\PartnerValidator;

/**
 * Fiches partenaire (M01) et candidature (P17) dans l'administration.
 */
class Admin implements ArgumentInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PartnerManagement $partnerManagement,
        private readonly AllowedCountries $allowedCountries,
        private readonly UrlInterface $url
    ) {
    }

    public function getManagement(): PartnerManagement
    {
        return $this->partnerManagement;
    }

    public function getPartner(): ?Partner
    {
        $partnerId = (int) $this->request->getParam('id');
        if ($partnerId <= 0) {
            return null;
        }
        try {
            return $this->partnerManagement->loadPartner($partnerId);
        } catch (LocalizedException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getApplication(): ?array
    {
        return $this->partnerManagement->getApplication((int) $this->request->getParam('id'));
    }

    /**
     * @return string[]
     */
    public function getCountries(): array
    {
        $countries = $this->allowedCountries->getAllowedCountries();
        sort($countries);

        return $countries;
    }

    /**
     * Choix de mode de vente du formulaire, à partir des modes d'une boutique.
     *
     * @param string[] $modes
     */
    public function getSalesModeChoice(array $modes): string
    {
        return count($modes) > 1 ? 'both' : (string) (reset($modes) ?: '');
    }

    public function getSalesModeLabel(string $modes): string
    {
        $labels = ['dropship' => (string) __('Dropshipping'), 'stock' => (string) __('Achat de stock')];

        return implode(' + ', array_map(static fn ($mode) => $labels[$mode] ?? $mode, array_filter(explode(',', $modes))));
    }

    public function getVolumeLabel(?string $volume): string
    {
        return $volume ? (string) __(PartnerValidator::VOLUMES[$volume] ?? $volume) : '—';
    }

    public function getStatusLabel(string $status): string
    {
        return [
            'active' => (string) __('Actif'),
            'pending' => (string) __('En attente'),
            'suspended' => (string) __('Suspendu'),
            'rejected' => (string) __('Refusé'),
            'approved' => (string) __('Acceptée'),
        ][$status] ?? $status;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getUrl(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }
}
