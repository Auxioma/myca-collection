<?php

declare(strict_types=1);

namespace Myca\Passport\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Passport\Model\LotFields;
use Myca\Passport\Model\PassportManagement;
use Myca\Passport\Model\PassportToken;
use Myca\Passport\Model\QrCode;

/**
 * Fiche d'un lot et outil de vérification (administration).
 */
class Admin implements ArgumentInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PassportManagement $passportManagement,
        private readonly LotFields $lotFields,
        private readonly PassportToken $token,
        private readonly QrCode $qrCode,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLot(): ?array
    {
        $lotId = (int) $this->request->getParam('id');

        return $lotId > 0 ? $this->passportManagement->getLot($lotId) : null;
    }

    /**
     * @return array<string, string>
     */
    public function getFields(): array
    {
        return $this->lotFields->getFields();
    }

    /**
     * @return array<string, string>
     */
    public function getStatusLabels(): array
    {
        return $this->lotFields->getStatusLabels();
    }

    /**
     * @param array<string, mixed>|null $lot
     */
    public function getStatus(?array $lot, string $field): string
    {
        return $this->lotFields->getStatus($lot ? $this->passportManagement->getStatuses($lot) : [], $field);
    }

    /**
     * @param array<string, mixed> $lot
     */
    public function canCertify(array $lot): bool
    {
        return $this->lotFields->canCertify($lot, $this->passportManagement->getStatuses($lot));
    }

    /**
     * @param array<string, mixed> $lot
     */
    public function getProductLabel(array $lot): string
    {
        try {
            $product = $this->passportManagement->getProduct((int) $lot['product_id']);
        } catch (LocalizedException) {
            return (string) $lot['product_id'];
        }

        return $product->getName() . ' (' . $product->getSku() . ')';
    }

    /**
     * @param array<string, mixed> $lot
     * @return array<string, string> sku → libellé des variantes du modèle
     */
    public function getVariants(array $lot): array
    {
        try {
            $product = $this->passportManagement->getProduct((int) $lot['product_id']);
        } catch (LocalizedException) {
            return [];
        }
        $type = $product->getTypeInstance();
        $variants = [];
        /** @var Product $variant */
        foreach ($type instanceof Configurable ? $type->getUsedProducts($product) : [$product] as $variant) {
            $variants[(string) $variant->getSku()] = (string) $variant->getName();
        }

        return $variants;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPassports(int $lotId): array
    {
        return $this->passportManagement->getPassports($lotId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $lotId): array
    {
        return $this->passportManagement->getHistory($lotId);
    }

    /**
     * Passeport recherché par le support (identifiant, numéro MYCA-… ou URL du QR).
     *
     * @return array<string, mixed>|null
     */
    public function getVerified(): ?array
    {
        $code = trim((string) $this->request->getParam('code'));

        return $code === '' ? null : $this->passportManagement->resolve($code);
    }

    public function getSearchedCode(): string
    {
        return trim((string) $this->request->getParam('code'));
    }

    /**
     * @return array{visitors_24h: int, total: int, suspicious: bool}
     */
    public function getScanStats(int $passportId): array
    {
        return $this->passportManagement->getScanStats($passportId);
    }

    public function formatNumber(string $token): string
    {
        return $this->token->format($token);
    }

    public function getPublicUrl(string $token): string
    {
        return $this->qrCode->getPublicUrl($token);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getUrl(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }
}
