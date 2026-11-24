<?php

declare(strict_types=1);

namespace Myca\Partner\Model;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * E-mails des comptes partenaires : accusé de réception de la candidature, alerte à l'équipe MYCA, acceptation,
 * refus, invitation d'un compte ajouté par MYCA. Les modèles se personnalisent dans Marketing > Modèles d'e-mails,
 * puis se choisissent dans Magasins > Configuration > MYCA > Espace partenaire > Candidatures et e-mails.
 *
 * Un e-mail qui ne part pas n'annule jamais l'action : l'erreur est journalisée.
 */
class PartnerMailer
{
    public const XML_SENDER = 'myca_partner/applications/sender';
    public const XML_TEAM_EMAIL = 'myca_partner/applications/team_email';
    public const XML_TEMPLATE_RECEIVED = 'myca_partner/applications/received_template';
    public const XML_TEMPLATE_TEAM = 'myca_partner/applications/team_template';
    public const XML_TEMPLATE_APPROVED = 'myca_partner/applications/approved_template';
    public const XML_TEMPLATE_REJECTED = 'myca_partner/applications/rejected_template';
    public const XML_TEMPLATE_INVITATION = 'myca_partner/applications/invitation_template';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly BackendUrl $backendUrl,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Accusé de réception envoyé à la boutique candidate.
     *
     * @param array<string, mixed> $application
     */
    public function sendApplicationReceived(array $application): void
    {
        $this->send(self::XML_TEMPLATE_RECEIVED, [(string) $application['email']], $this->applicationData($application));
    }

    /**
     * Alerte à l'équipe MYCA (une ou plusieurs adresses) avec le lien vers la candidature dans l'administration.
     *
     * @param array<string, mixed> $application
     */
    public function notifyTeam(array $application): void
    {
        $recipients = self::parseRecipients((string) $this->config(self::XML_TEAM_EMAIL));
        if ($recipients === []) {
            $this->logger->warning('Myca_Partner : aucune adresse MYCA configurée pour les nouvelles candidatures.');

            return;
        }
        $data = $this->applicationData($application);
        $data['admin_url'] = $this->backendUrl->getUrl(
            'myca_partner/application/view',
            ['id' => (int) $application['application_id'], '_nosecret' => true]
        );
        $this->send(self::XML_TEMPLATE_TEAM, $recipients, $data, (string) $application['email']);
    }

    /**
     * Candidature acceptée : lien pour définir le mot de passe (compte créé) ou pour se connecter (compte existant).
     *
     * @param array<string, mixed> $application
     */
    public function sendApplicationApproved(array $application, string $partnerCode, ?int $customerId, ?string $token): void
    {
        $data = $this->applicationData($application);
        $data += $this->accountLinks($customerId, $token);
        $data['partner_code'] = $partnerCode;
        $this->send(self::XML_TEMPLATE_APPROVED, [(string) $application['email']], $data);
    }

    /**
     * @param array<string, mixed> $application
     */
    public function sendApplicationRejected(array $application, string $reason): void
    {
        $data = $this->applicationData($application);
        $data['reason'] = $reason;
        $this->send(self::XML_TEMPLATE_REJECTED, [(string) $application['email']], $data);
    }

    /**
     * Compte rattaché à une boutique par MYCA (création directe ou ajout d'un collaborateur).
     */
    public function sendInvitation(
        string $email,
        string $name,
        string $companyName,
        string $role,
        ?int $customerId,
        ?string $token
    ): void {
        $data = ['name' => $name, 'email' => $email, 'company_name' => $companyName,
            'role_label' => $role === PartnerManagement::ROLE_OWNER ? (string) __('titulaire') : (string) __('collaborateur')];
        $data += $this->accountLinks($customerId, $token);
        $this->send(self::XML_TEMPLATE_INVITATION, [$email], $data);
    }

    /**
     * Construit l'e-mail sans l'envoyer (utilisé par send() et pour vérifier le rendu).
     *
     * @param string[] $recipients
     * @param array<string, mixed> $data
     */
    public function build(string $templatePath, array $recipients, array $data, ?string $replyTo = null): TransportInterface
    {
        $store = $this->store();
        $this->transportBuilder
            ->setTemplateIdentifier((string) $this->config($templatePath))
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => (int) $store->getId()])
            ->setTemplateVars(['data' => new DataObject($data), 'store' => $store])
            ->setFromByScope((string) ($this->config(self::XML_SENDER) ?: 'general'), (int) $store->getId());
        foreach ($recipients as $recipient) {
            $this->transportBuilder->addTo($recipient);
        }
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $this->transportBuilder->setReplyTo($replyTo);
        }

        return $this->transportBuilder->getTransport();
    }

    /**
     * « a@x.fr, b@y.fr ; c@z.fr » → adresses valides, sans doublon.
     *
     * @return string[]
     */
    public static function parseRecipients(string $value): array
    {
        $emails = array_map('trim', preg_split('/[,;\s]+/', $value) ?: []);

        return array_values(array_unique(array_filter($emails, static fn (string $email) => (bool) filter_var($email, FILTER_VALIDATE_EMAIL))));
    }

    /**
     * @param string[] $recipients
     * @param array<string, mixed> $data
     */
    private function send(string $templatePath, array $recipients, array $data, ?string $replyTo = null): void
    {
        $this->inlineTranslation->suspend();
        try {
            $this->build($templatePath, $recipients, $data, $replyTo)->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->error('Myca_Partner : e-mail non envoyé (' . $templatePath . ') : ' . $e->getMessage());
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * @param array<string, mixed> $application
     * @return array<string, mixed>
     */
    private function applicationData(array $application): array
    {
        $modes = ['dropship' => (string) __('Dropshipping'), 'stock' => (string) __('Achat de stock')];
        $labels = array_map(
            static fn (string $mode) => $modes[$mode] ?? $mode,
            array_filter(explode(',', (string) ($application['sales_modes'] ?? '')))
        );

        return [
            'application_id' => (int) ($application['application_id'] ?? 0),
            'company_name' => (string) $application['company_name'],
            'contact_name' => (string) $application['contact_name'],
            'email' => (string) $application['email'],
            'phone' => (string) ($application['phone'] ?? ''),
            'siret' => (string) ($application['siret'] ?? ''),
            'vat_id' => (string) ($application['vat_id'] ?? ''),
            'city' => trim(($application['postcode'] ?? '') . ' ' . ($application['city'] ?? '') . ' ' . ($application['country_id'] ?? '')),
            'website' => (string) ($application['website'] ?? ''),
            'sales_modes' => implode(' + ', $labels),
            'annual_volume' => PartnerValidator::VOLUMES[(string) ($application['annual_volume'] ?? '')] ?? (string) __('Non précisé'),
            'message' => (string) ($application['message'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountLinks(?int $customerId, ?string $token): array
    {
        $store = $this->store();
        $links = [
            'login_url' => $store->getUrl('customer/account/login', ['_nosid' => true]),
            'partner_url' => $store->getUrl('partenaire', ['_nosid' => true]),
            'forgot_url' => $store->getUrl('customer/account/forgotpassword', ['_nosid' => true]),
            'link_hours' => max(1, (int) $this->scopeConfig->getValue('customer/password/reset_link_expiration_period')),
        ];
        if ($customerId === null || $token === null) {
            $links['existing_account'] = 1;
        } else {
            $links['set_password_url'] = $store->getUrl(
                'customer/account/createPassword',
                ['_query' => ['id' => $customerId, 'token' => $token], '_nosid' => true]
            );
        }

        return $links;
    }

    private function store(): Store
    {
        /** @var Store $store */
        $store = $this->storeManager->getDefaultStoreView() ?? $this->storeManager->getStore();

        return $store;
    }

    private function config(string $path): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, (int) $this->store()->getId());
    }
}
