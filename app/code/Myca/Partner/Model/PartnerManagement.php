<?php

declare(strict_types=1);

namespace Myca\Partner\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\AccountManagement;
use Magento\Directory\Model\AllowedCountries;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use Magento\Store\Model\StoreManagerInterface;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Api\PartnerRepositoryInterface;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;

/**
 * Comptes partenaires B2B : candidature P17, acceptation ou refus par MYCA, création directe dans l'administration,
 * comptes rattachés (titulaire, collaborateurs), activation et suspension.
 *
 * Un compte partenaire = un compte client du groupe « Partenaire MYCA » + une fiche boutique active + le
 * rattachement du compte à la boutique. Les trois sont créés ensemble, dans une même transaction.
 */
class PartnerManagement
{
    public const APPLICATION_PENDING = 'pending';
    public const APPLICATION_APPROVED = 'approved';
    public const APPLICATION_REJECTED = 'rejected';

    public const ROLE_OWNER = 'owner';
    public const ROLE_STAFF = 'staff';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly PartnerValidator $validator,
        private readonly PartnerFactory $partnerFactory,
        private readonly PartnerRepositoryInterface $partnerRepository,
        private readonly PartnerResource $partnerResource,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly AccountManagement $accountManagement,
        private readonly AllowedCountries $allowedCountries,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Random $random,
        private readonly PartnerMailer $mailer
    ) {
    }

    /**
     * Candidature déposée depuis « Devenir partenaire » (P17). Aucune boutique n'est créée à ce stade.
     *
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    public function submitApplication(array $data): int
    {
        $row = $this->validateCompany($data);
        $email = $row['email'];
        $connection = $this->connection();
        $pending = $connection->fetchOne(
            $connection->select()->from($this->table('myca_partner_application'), ['application_id'])
                ->where('status = ?', self::APPLICATION_PENDING)
                ->where('siret = ? OR email = ?', $row['siret'], $email)
        );
        if ($pending) {
            throw new LocalizedException(__('Une candidature pour cette société ou cet e-mail est déjà en cours d’examen.'));
        }
        if ($this->findPartnerIdBySiret($row['siret']) !== null) {
            throw new LocalizedException(__('Cette société est déjà partenaire MYCA. Contactez le Service Partenaire B2B.'));
        }
        if (empty($data['terms']) || empty($data['contact_consent'])) {
            throw new LocalizedException(__('Merci d’accepter les conditions de vente professionnelles et d’être recontacté par MYCA.'));
        }
        $volume = (string) ($data['annual_volume'] ?? '');
        $row += [
            'contact_name' => $this->requireText($data, 'contact_name', __('Indiquez le nom du contact.')),
            'website' => $this->website((string) ($data['website'] ?? '')),
            'annual_volume' => isset(PartnerValidator::VOLUMES[$volume]) ? $volume : null,
            'message' => mb_substr(trim((string) ($data['message'] ?? '')), 0, 2000) ?: null,
            'terms_accepted_at' => gmdate('Y-m-d H:i:s'),
            'contact_consent' => 1,
            'status' => self::APPLICATION_PENDING,
        ];
        $row['sales_modes'] = implode(',', $row['sales_modes']);
        $applicationId = $this->insert('myca_partner_application', $row);
        $application = ['application_id' => $applicationId] + $row;
        $this->mailer->sendApplicationReceived($application);
        $this->mailer->notifyTeam($application);

        return $applicationId;
    }

    /**
     * Acceptation : crée la boutique active et le compte du titulaire (ou rattache son compte client existant).
     *
     * @throws LocalizedException
     */
    public function approveApplication(int $applicationId, int $adminUserId): int
    {
        $application = $this->getApplication($applicationId);
        if ($application === null || $application['status'] !== self::APPLICATION_PENDING) {
            throw new LocalizedException(__('Cette candidature a déjà été traitée.'));
        }
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            [$partnerId, $customerId, $token] = $this->createPartner(
                array_merge($application, ['sales_modes' => explode(',', (string) $application['sales_modes'])]),
                (string) $application['email'],
                (string) $application['contact_name']
            );
            $connection->update($this->table('myca_partner_application'), [
                'status' => self::APPLICATION_APPROVED,
                'partner_id' => $partnerId,
                'reviewed_by' => $adminUserId ?: null,
                'reviewed_at' => gmdate('Y-m-d H:i:s'),
            ], ['application_id = ?' => $applicationId]);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $this->mailer->sendApplicationApproved($application, $this->loadPartner($partnerId)->getCode(), $customerId, $token);

        return $partnerId;
    }

    /**
     * @throws LocalizedException
     */
    public function rejectApplication(int $applicationId, string $reason, int $adminUserId): void
    {
        $application = $this->getApplication($applicationId);
        if ($application === null || $application['status'] !== self::APPLICATION_PENDING) {
            throw new LocalizedException(__('Cette candidature a déjà été traitée.'));
        }
        if (trim($reason) === '') {
            throw new LocalizedException(__('Indiquez le motif du refus.'));
        }
        $this->connection()->update($this->table('myca_partner_application'), [
            'status' => self::APPLICATION_REJECTED,
            'rejection_reason' => mb_substr(trim($reason), 0, 2000),
            'reviewed_by' => $adminUserId ?: null,
            'reviewed_at' => gmdate('Y-m-d H:i:s'),
        ], ['application_id = ?' => $applicationId]);
        $this->mailer->sendApplicationRejected($application, trim($reason));
    }

    /**
     * Création directe d'une boutique active et de son titulaire, depuis l'administration.
     *
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    public function createFromAdmin(array $data): int
    {
        $row = $this->validateCompany($data);
        if ($this->findPartnerIdBySiret($row['siret']) !== null) {
            throw new LocalizedException(__('Une boutique existe déjà avec ce SIRET.'));
        }
        $contact = $this->requireText($data, 'contact_name', __('Indiquez le nom du titulaire.'));
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            [$partnerId, $customerId, $token] = $this->createPartner($row, $row['email'], $contact);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $this->mailer->sendInvitation($row['email'], $contact, $row['company_name'], self::ROLE_OWNER, $customerId, $token);

        return $partnerId;
    }

    /**
     * Modification des informations d'une boutique par MYCA.
     *
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    public function updatePartner(int $partnerId, array $data): void
    {
        $partner = $this->loadPartner($partnerId);
        $row = $this->validateCompany($data);
        $existing = $this->findPartnerIdBySiret($row['siret']);
        if ($existing !== null && $existing !== $partnerId) {
            throw new LocalizedException(__('Une autre boutique utilise déjà ce SIRET.'));
        }
        $partner->addData([
            'company_name' => $row['company_name'],
            'siret' => $row['siret'],
            'vat_id' => $row['vat_id'],
            'street' => $row['street'],
            'postcode' => $row['postcode'],
            'city' => $row['city'],
            'country_id' => $row['country_id'],
            'phone' => $row['phone'],
            'email' => $row['email'],
        ]);
        $partner->setSalesModes($row['sales_modes']);
        $partner->setData('dropship_discount', $this->discount($data, 'dropship_discount'));
        $partner->setData('stock_discount', $this->discount($data, 'stock_discount'));
        $this->partnerRepository->save($partner);
    }

    /**
     * Remise contractuelle facultative (0 à 90 %) ; vide = tarif de la configuration.
     *
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    private function discount(array $data, string $field): ?float
    {
        $value = str_replace(',', '.', trim((string) ($data[$field] ?? '')));
        if ($value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 90) {
            throw new LocalizedException(__('La remise contractuelle doit être comprise entre 0 et 90 %.'));
        }

        return round((float) $value, 2);
    }

    /**
     * Activation, suspension ou réactivation. Une boutique suspendue n'ouvre plus l'espace partenaire.
     *
     * @throws LocalizedException
     */
    public function setStatus(int $partnerId, string $status, string $reason): void
    {
        if (!in_array($status, [PartnerInterface::STATUS_ACTIVE, PartnerInterface::STATUS_SUSPENDED], true)) {
            throw new LocalizedException(__('Statut inconnu.'));
        }
        if ($status === PartnerInterface::STATUS_SUSPENDED && trim($reason) === '') {
            throw new LocalizedException(__('Indiquez le motif de la suspension.'));
        }
        $partner = $this->loadPartner($partnerId);
        $partner->setStatus($status);
        $partner->setData($status === PartnerInterface::STATUS_ACTIVE ? 'approved_at' : 'suspended_at', gmdate('Y-m-d H:i:s'));
        if ($status === PartnerInterface::STATUS_ACTIVE) {
            $partner->setData('suspended_at', null);
        }
        $this->partnerRepository->save($partner);
    }

    /**
     * Rattache un compte (créé s'il n'existe pas) à la boutique, en titulaire ou en collaborateur.
     *
     * @throws LocalizedException
     */
    public function addUser(int $partnerId, string $email, string $name, string $role): int
    {
        $partner = $this->loadPartner($partnerId);
        if (!in_array($role, [self::ROLE_OWNER, self::ROLE_STAFF], true)) {
            throw new LocalizedException(__('Rôle inconnu.'));
        }
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Indiquez une adresse e-mail valide.'));
        }
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            [$customerId, $token] = $this->attachCustomer($partnerId, $email, trim($name) ?: $email, $role);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $this->mailer->sendInvitation($email, trim($name) ?: $email, (string) $partner->getCompanyName(), $role, $customerId, $token);

        return $customerId;
    }

    /**
     * Retire un compte de la boutique : il redevient un compte client B2C. Le dernier titulaire ne peut pas partir.
     *
     * @throws LocalizedException
     */
    public function removeUser(int $partnerId, int $customerId): void
    {
        $users = $this->getUsers($partnerId);
        $owners = array_filter($users, static fn (array $user) => $user['role'] === self::ROLE_OWNER);
        $target = array_values(array_filter($users, static fn (array $user) => (int) $user['customer_id'] === $customerId))[0] ?? null;
        if ($target === null) {
            throw new LocalizedException(__('Ce compte n’est pas rattaché à la boutique.'));
        }
        if ($target['role'] === self::ROLE_OWNER && count($owners) <= 1) {
            throw new LocalizedException(__('La boutique doit garder au moins un titulaire.'));
        }
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $connection->delete($this->table('myca_partner_user'), ['partner_id = ?' => $partnerId, 'customer_id = ?' => $customerId]);
            $customer = $this->customerRepository->getById($customerId);
            $customer->setGroupId((int) $this->scopeConfig->getValue('customer/create_account/default_group') ?: 1);
            $this->customerRepository->save($customer);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getApplication(int $applicationId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->table('myca_partner_application'))->where('application_id = ?', $applicationId)
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUsers(int $partnerId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()
                ->from(['u' => $this->table('myca_partner_user')], ['customer_id', 'role', 'created_at'])
                ->join(['c' => $this->table('customer_entity')], 'c.entity_id = u.customer_id', ['email', 'firstname', 'lastname'])
                ->where('u.partner_id = ?', $partnerId)
                ->order(['u.role', 'c.lastname'])
        );
    }

    /**
     * @return array{orders_30d: int, amount_30d: float, orders_total: int}
     */
    public function getActivity(int $partnerId): array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from(['po' => $this->table('myca_partner_order')], [])
                ->join(['o' => $this->table('sales_order')], 'o.entity_id = po.order_id', [
                    'orders_total' => new \Zend_Db_Expr('COUNT(*)'),
                    'orders_30d' => new \Zend_Db_Expr("SUM(o.created_at >= NOW() - INTERVAL 30 DAY AND o.state <> 'canceled')"),
                    'amount_30d' => new \Zend_Db_Expr("SUM(IF(o.created_at >= NOW() - INTERVAL 30 DAY AND o.state <> 'canceled', o.base_subtotal - ABS(o.base_discount_amount), 0))"),
                ])
                ->where('po.partner_id = ?', $partnerId)
        );

        return [
            'orders_30d' => (int) ($row['orders_30d'] ?? 0),
            'amount_30d' => round((float) ($row['amount_30d'] ?? 0), 2),
            'orders_total' => (int) ($row['orders_total'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentOrders(int $partnerId, int $limit = 5): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()
                ->from(['po' => $this->table('myca_partner_order')], [])
                ->join(['o' => $this->table('sales_order')], 'o.entity_id = po.order_id', ['entity_id', 'increment_id', 'created_at', 'status', 'base_grand_total'])
                ->where('po.partner_id = ?', $partnerId)
                ->order('o.entity_id DESC')
                ->limit($limit)
        );
    }

    public function loadPartner(int $partnerId): Partner
    {
        try {
            $partner = $this->partnerRepository->getById($partnerId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Cette boutique est introuvable.'));
        }
        if (!$partner instanceof Partner) {
            throw new LocalizedException(__('Cette boutique est introuvable.'));
        }

        return $partner;
    }

    /**
     * @param array<string, mixed> $data données validées (validateCompany) ou candidature
     * @return array{0: int, 1: int, 2: ?string} boutique, compte du titulaire, jeton « définir le mot de passe »
     */
    private function createPartner(array $data, string $ownerEmail, string $ownerName): array
    {
        $partner = $this->partnerFactory->create();
        $partner->setData([
            'code' => $this->nextCode(),
            'company_name' => $data['company_name'],
            'siret' => $data['siret'],
            'vat_id' => $data['vat_id'] ?: null,
            'street' => $data['street'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'city' => $data['city'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $ownerEmail,
            'status' => PartnerInterface::STATUS_ACTIVE,
            'customer_group_id' => $this->getPartnerGroupId(),
            'approved_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $partner->setSalesModes((array) $data['sales_modes']);
        $this->partnerRepository->save($partner);
        $partnerId = (int) $partner->getId();
        [$customerId, $token] = $this->attachCustomer($partnerId, $ownerEmail, $ownerName, self::ROLE_OWNER);

        return [$partnerId, $customerId, $token];
    }

    /**
     * Compte client du collaborateur : réutilisé s'il existe (et n'appartient à aucune autre boutique), créé sinon.
     * Un compte créé n'a pas de mot de passe : le jeton renvoyé sert au lien « définir mon mot de passe » de l'e-mail
     * MYCA (aucun e-mail Magento n'est envoyé en plus).
     *
     * @return array{0: int, 1: ?string} compte, jeton (null si le compte existait déjà)
     */
    private function attachCustomer(int $partnerId, string $email, string $name, string $role): array
    {
        $token = null;
        $websiteId = (int) $this->storeManager->getDefaultStoreView()?->getWebsiteId();
        $customer = null;
        try {
            $customer = $this->customerRepository->get($email, $websiteId);
        } catch (NoSuchEntityException) {
            $customer = null;
        }
        if ($customer !== null) {
            $current = $this->partnerResource->getPartnerIdByCustomerId((int) $customer->getId());
            if ($current !== null && $current !== $partnerId) {
                throw new LocalizedException(__('Le compte %1 appartient déjà à une autre boutique.', $email));
            }
            $customer->setGroupId($this->getPartnerGroupId());
            $customer = $this->customerRepository->save($customer);
        } else {
            [$firstname, $lastname] = $this->validator->splitName($name);
            /** @var CustomerInterface $newCustomer */
            $newCustomer = $this->customerFactory->create();
            $newCustomer->setEmail($email);
            $newCustomer->setFirstname(mb_substr($firstname, 0, 255));
            $newCustomer->setLastname(mb_substr($lastname, 0, 255));
            $newCustomer->setWebsiteId($websiteId);
            $newCustomer->setGroupId($this->getPartnerGroupId());
            $newCustomer->setStoreId((int) $this->storeManager->getDefaultStoreView()?->getId());
            $customer = $this->customerRepository->save($newCustomer);
            $token = $this->random->getUniqueHash();
            $this->accountManagement->changeResetPasswordLinkToken($customer, $token);
        }
        $this->partnerResource->linkCustomer($partnerId, (int) $customer->getId(), $role);

        return [(int) $customer->getId(), $token];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{company_name: string, siret: string, vat_id: ?string, email: string, phone: string,
     *     street: ?string, postcode: ?string, city: ?string, country_id: string, sales_modes: string[]}
     * @throws LocalizedException
     */
    private function validateCompany(array $data): array
    {
        $company = $this->requireText($data, 'company_name', __('Indiquez la raison sociale.'));
        $siret = $this->validator->normalizeSiret((string) ($data['siret'] ?? ''));
        if ($siret === null) {
            throw new LocalizedException(__('Le SIRET doit comporter 14 chiffres valides.'));
        }
        $vatInput = trim((string) ($data['vat_id'] ?? ''));
        $vat = $vatInput === '' ? null : $this->validator->normalizeVat($vatInput, $siret);
        if ($vatInput !== '' && $vat === null) {
            throw new LocalizedException(__('Le n° de TVA intracommunautaire est invalide (pour la France : FR + clé + SIREN du SIRET).'));
        }
        $email = trim((string) ($data['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Indiquez une adresse e-mail professionnelle valide.'));
        }
        $phone = $this->validator->normalizePhone((string) ($data['phone'] ?? ''));
        if ($phone === null) {
            throw new LocalizedException(__('Indiquez un numéro de téléphone valide.'));
        }
        $country = strtoupper(trim((string) ($data['country_id'] ?? '')));
        if (!in_array($country, $this->allowedCountries->getAllowedCountries(), true)) {
            throw new LocalizedException(__('Choisissez un pays.'));
        }
        $modes = $this->validator->parseSalesModes((string) ($data['sales_mode'] ?? ''));
        if ($modes === null) {
            throw new LocalizedException(__('Choisissez le mode de vente souhaité.'));
        }

        return [
            'company_name' => $company,
            'siret' => $siret,
            'vat_id' => $vat,
            'email' => $email,
            'phone' => $phone,
            'street' => trim((string) ($data['street'] ?? '')) ?: null,
            'postcode' => trim((string) ($data['postcode'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'country_id' => $country,
            'sales_modes' => $modes,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireText(array $data, string $field, \Magento\Framework\Phrase $message): string
    {
        $value = trim((string) ($data[$field] ?? ''));
        if ($value === '') {
            throw new LocalizedException($message);
        }

        return mb_substr($value, 0, 255);
    }

    private function website(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new LocalizedException(__('L’adresse du site web est invalide.'));
        }

        return mb_substr($url, 0, 255);
    }

    private function findPartnerIdBySiret(string $siret): ?int
    {
        $connection = $this->connection();
        $id = $connection->fetchOne($connection->select()->from($this->table('myca_partner'), ['partner_id'])->where('siret = ?', $siret));

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * N° partenaire suivant : PART-000001, PART-000002… à partir du plus grand numéro existant.
     */
    private function nextCode(): string
    {
        $connection = $this->connection();
        $max = (int) $connection->fetchOne(
            $connection->select()->from($this->table('myca_partner'), [new \Zend_Db_Expr("MAX(CAST(SUBSTRING(code, 6) AS UNSIGNED))")])
                ->where('code LIKE ?', 'PART-%')
        );

        return sprintf('PART-%06d', $max + 1);
    }

    private function getPartnerGroupId(): int
    {
        $groups = array_filter(array_map('intval', explode(',', (string) $this->scopeConfig->getValue('myca_core/identity/partner_groups'))));
        $group = reset($groups);
        if (!$group) {
            throw new LocalizedException(__('Le groupe client « Partenaire MYCA » n’est pas configuré.'));
        }

        return $group;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): int
    {
        $connection = $this->connection();
        $connection->insert($this->table($table), $row);

        return $connection instanceof Mysql ? (int) $connection->lastInsertId($this->table($table)) : 0;
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
