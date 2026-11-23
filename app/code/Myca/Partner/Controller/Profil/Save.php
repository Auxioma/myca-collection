<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Profil;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\AllowedCountries;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Api\PartnerRepositoryInterface;
use Myca\Partner\Controller\AbstractSave;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\Partner;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;

/**
 * M25 : informations de la boutique. Seul le titulaire du compte (rôle « owner ») peut les modifier.
 * N° partenaire, statut, SIRET et TVA restent contrôlés par MYCA.
 */
class Save extends AbstractSave
{
    protected const BACK_PATH = 'partenaire/profil';

    private const DESCRIPTION_MAX = 500;

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        CustomerSession $customerSession,
        CurrentPartner $currentPartner,
        ManagerInterface $messageManager,
        private readonly PartnerRepositoryInterface $partnerRepository,
        private readonly PartnerResource $partnerResource,
        private readonly AllowedCountries $allowedCountries
    ) {
        parent::__construct($request, $redirectFactory, $customerSession, $currentPartner, $messageManager);
    }

    protected function save(PartnerInterface $partner, int $customerId): void
    {
        if (!$this->isOwner((int) $partner->getId(), $customerId)) {
            $this->messageManager->addErrorMessage(__('Seul le titulaire du compte peut modifier le profil de la boutique.'));

            return;
        }
        try {
            /** @var Partner $partner */
            $partner->addData($this->collect());
            $this->partnerRepository->save($partner);
            $this->messageManager->addSuccessMessage(__('Le profil de la boutique a été enregistré.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }

    /**
     * @return array<string, string|int|null>
     * @throws LocalizedException
     */
    private function collect(): array
    {
        $text = fn (string $field, int $max) => mb_substr(trim((string) $this->request->getParam($field)), 0, $max);

        $name = $text('company_name', 255);
        if ($name === '') {
            throw new LocalizedException(__('Le nom de la boutique est obligatoire.'));
        }
        $description = trim((string) $this->request->getParam('description'));
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            throw new LocalizedException(__('La présentation ne doit pas dépasser %1 caractères.', self::DESCRIPTION_MAX));
        }
        $email = $text('email', 255);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new LocalizedException(__('L’adresse e-mail de la boutique n’est pas valide.'));
        }
        $phone = $text('phone', 32);
        if ($phone !== '' && !preg_match('/^\+?[0-9 .()-]{6,}$/', $phone)) {
            throw new LocalizedException(__('Le numéro de téléphone n’est pas valide.'));
        }
        $country = strtoupper($text('country_id', 2));
        if ($country !== '' && !in_array($country, $this->allowedCountries->getAllowedCountries(), true)) {
            throw new LocalizedException(__('Ce pays n’est pas proposé.'));
        }
        $opening = $text('opening_date', 10);
        if ($opening !== '' && \DateTimeImmutable::createFromFormat('!Y-m-d', $opening) === false) {
            throw new LocalizedException(__('La date d’ouverture n’est pas valide.'));
        }

        return [
            'company_name' => $name,
            'description' => $description === '' ? null : $description,
            'street' => $text('street', 255) ?: null,
            'postcode' => $text('postcode', 16) ?: null,
            'city' => $text('city', 128) ?: null,
            'country_id' => $country ?: null,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'opening_date' => $opening ?: null,
            'show_in_directory' => (int) (bool) $this->request->getParam('show_in_directory'),
            'notify_new_collections' => (int) (bool) $this->request->getParam('notify_new_collections'),
            'notify_promotions' => (int) (bool) $this->request->getParam('notify_promotions'),
            'allow_contact' => (int) (bool) $this->request->getParam('allow_contact'),
            'hide_contact_details' => (int) (bool) $this->request->getParam('hide_contact_details'),
        ];
    }

    private function isOwner(int $partnerId, int $customerId): bool
    {
        return $this->partnerResource->getUserRole($partnerId, $customerId) === 'owner';
    }
}
