<?php

declare(strict_types=1);

namespace Myca\Passport\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Passport\Controller\View\Index as PassportPage;
use Myca\Passport\Model\LotFields;
use Myca\Passport\Model\PassportManagement;
use Myca\Passport\Model\PassportToken;
use Myca\Passport\Model\QrCode;

/**
 * M50 (page publique du passeport) et « Mes passeports MYCA ». Aucune donnée personnelle n'est affichée sur la
 * page publique.
 */
class Passport implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly PassportManagement $passportManagement,
        private readonly LotFields $lotFields,
        private readonly PassportToken $token,
        private readonly QrCode $qrCode,
        private readonly ImageHelper $imageHelper,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CustomerSession $customerSession,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPassport(): ?array
    {
        $passport = $this->registry->registry(PassportPage::REGISTRY_KEY);

        return is_array($passport) ? $passport : null;
    }

    /**
     * @param array<string, mixed> $passport
     * @return array<string, mixed>
     */
    public function getLot(array $passport): array
    {
        return $this->passportManagement->getLot((int) $passport['lot_id']) ?? [];
    }

    /**
     * @param array<string, mixed> $lot
     */
    public function getProduct(array $lot): ?Product
    {
        try {
            return $this->passportManagement->getProduct((int) ($lot['product_id'] ?? 0));
        } catch (LocalizedException) {
            return null;
        }
    }

    public function getImageUrl(?Product $product): string
    {
        return $product === null ? '' : $this->imageHelper->init($product, 'product_page_image_medium')->getUrl();
    }

    /**
     * Lignes du passeport : libellé, valeur publiable (null si en cours de validation) et statut de la donnée.
     *
     * @param array<string, mixed> $lot
     * @param array<string, mixed> $passport
     * @return array<int, array{label: string, value: ?string, status: string}>
     */
    public function getRows(array $lot, array $passport, ?Product $product): array
    {
        $statuses = $this->passportManagement->getStatuses($lot);
        $rows = [[
            'label' => (string) __('Modèle'),
            'value' => $product === null ? null : (string) $product->getName(),
            'status' => LotFields::CERTIFIED,
        ]];
        if (!empty($passport['variant_label'])) {
            $rows[] = ['label' => (string) __('Article'), 'value' => (string) $passport['variant_label'], 'status' => LotFields::CERTIFIED];
        }
        foreach ($this->lotFields->getFields() as $field => $label) {
            $value = $this->lotFields->getPublicValue($statuses, $field, $lot[$field] === null ? null : (string) $lot[$field]);
            if ($field === 'manufactured_on' && $value !== null) {
                $value = (string) __('Lot %1 · %2', $lot['lot_code'], substr($value, 5, 2) . '/' . substr($value, 0, 4));
            }
            $rows[] = ['label' => $label, 'value' => $value, 'status' => $this->lotFields->getStatus($statuses, $field)];
        }
        if (!empty($passport['serial_number'])) {
            $rows[] = ['label' => (string) __('N° de série'), 'value' => (string) $passport['serial_number'], 'status' => LotFields::CERTIFIED];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function getStatusLabels(): array
    {
        return $this->lotFields->getStatusLabels();
    }

    /**
     * Certificat : seulement si l'option est activée, le passeport actif et les données d'identité certifiées.
     *
     * @param array<string, mixed> $passport
     * @param array<string, mixed> $lot
     */
    public function hasCertificate(array $passport, array $lot): bool
    {
        return $this->scopeConfig->isSetFlag('myca_passport/general/certificate_enabled')
            && $passport['status'] === PassportManagement::STATUS_ACTIVE
            && $this->lotFields->canCertify($lot, $this->passportManagement->getStatuses($lot));
    }

    public function formatNumber(string $token): string
    {
        return $this->token->format($token);
    }

    public function getQrSvg(string $token): string
    {
        return $this->qrCode->getSvg($token);
    }

    public function getPublicUrl(string $token): string
    {
        return $this->qrCode->getPublicUrl($token);
    }

    /**
     * @return array{visitors_24h: int, total: int, suspicious: bool}
     */
    public function getScanStats(int $passportId): array
    {
        return $this->passportManagement->getScanStats($passportId);
    }

    public function wasReplaced(): bool
    {
        return (bool) $this->request->getParam('remplace');
    }

    public function isLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOwned(): array
    {
        return $this->passportManagement->getOwned((int) $this->customerSession->getCustomerId());
    }
}
