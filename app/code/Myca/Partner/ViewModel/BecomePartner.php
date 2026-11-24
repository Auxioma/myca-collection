<?php

declare(strict_types=1);

namespace Myca\Partner\ViewModel;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Model\AllowedCountries;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Partner\Controller\Candidature\Envoyer;
use Myca\Partner\Model\PartnerValidator;

/**
 * P17 — données du formulaire « Devenir partenaire » : pays desservis, tranches de volume, saisie conservée.
 */
class BecomePartner implements ArgumentInterface
{
    /** @var array<string, mixed>|null */
    private ?array $previous = null;

    public function __construct(
        private readonly AllowedCountries $allowedCountries,
        private readonly CountryInformationAcquirerInterface $countryInformation,
        private readonly DataPersistorInterface $session
    ) {
    }

    /**
     * @return array<string, string> code → nom, la France en premier
     */
    public function getCountries(): array
    {
        $countries = [];
        foreach ($this->allowedCountries->getAllowedCountries() as $code) {
            try {
                $countries[$code] = (string) $this->countryInformation->getCountryInfo($code)->getFullNameLocale();
            } catch (\Exception) {
                $countries[$code] = $code;
            }
        }
        asort($countries);

        return isset($countries['FR']) ? ['FR' => $countries['FR']] + $countries : $countries;
    }

    /**
     * @return array<string, string>
     */
    public function getVolumes(): array
    {
        return array_map(static fn (string $label) => (string) __($label), PartnerValidator::VOLUMES);
    }

    /**
     * Valeur saisie avant une erreur (la saisie n'est conservée qu'une fois).
     */
    public function getValue(string $field, string $default = ''): string
    {
        if ($this->previous === null) {
            $this->previous = (array) $this->session->get(Envoyer::SESSION_KEY);
            $this->session->clear(Envoyer::SESSION_KEY);
        }

        return (string) ($this->previous[$field] ?? $default);
    }
}
