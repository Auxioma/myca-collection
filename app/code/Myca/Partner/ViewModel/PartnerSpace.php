<?php

declare(strict_types=1);

namespace Myca\Partner\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableType;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\NotificationPreferences;
use Myca\Partner\Model\PartnerData;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;

/**
 * Données de l'espace partenaire (M16–M27), limitées au partenaire actif du client connecté.
 * Les gabarits ne sont rendus qu'après le contrôle d'accès des contrôleurs (Controller\AbstractPage).
 */
class PartnerSpace implements ArgumentInterface
{
    /**
     * Rubriques livrées, dans l'ordre de la maquette. « Passer une commande » (M17), « Mes marges & ventes » (M21)
     * et « Retours & SAV » (M23) apparaîtront avec le tunnel de commande et Myca_Rma.
     */
    private const NAVIGATION = [
        ['partenaire', 'partenaire_index_index', 'Tableau de bord', 'home'],
        ['__catalog__', '', 'Catalogue MYCA', 'shirt'],
        ['partenaire/commandes', 'partenaire_commandes_index', 'Mes commandes', 'layers'],
        ['partenaire/livraisons', 'partenaire_livraisons_index', 'Suivi des livraisons', 'truck'],
        ['partenaire/factures', 'partenaire_factures_index', 'Mes factures', 'file'],
        ['partenaire/clients', 'partenaire_clients_index', 'Mes clients', 'users'],
        ['partenaire/profil', 'partenaire_profil_index', 'Mon profil boutique', 'store'],
        ['partenaire/parametres', 'partenaire_parametres_index', 'Paramètres', 'settings'],
    ];

    public function __construct(
        private readonly CurrentPartner $currentPartner,
        private readonly PartnerData $partnerData,
        private readonly PartnerResource $partnerResource,
        private readonly NotificationPreferences $notificationPreferences,
        private readonly CustomerSession $customerSession,
        private readonly HttpRequest $request,
        private readonly UrlInterface $url,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelper $imageHelper,
        private readonly ConfigurableType $configurableType,
        private readonly CountryInformationAcquirerInterface $countryInformation
    ) {
    }

    public function getPartner(): ?PartnerInterface
    {
        return $this->currentPartner->getActive();
    }

    public function getPartnerId(): int
    {
        return (int) $this->currentPartner->getActiveId();
    }

    public function getField(string $field): string
    {
        $partner = $this->getPartner();

        return $partner instanceof \Magento\Framework\DataObject ? (string) $partner->getData($field) : '';
    }

    public function getLogoUrl(): string
    {
        $logo = $this->getPartner()?->getLogo();
        if ($logo === null) {
            return '';
        }
        $store = $this->storeManager->getStore();

        return $store instanceof \Magento\Store\Model\Store
            ? $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . ltrim($logo, '/')
            : '';
    }

    /**
     * Coordonnées du Service Partenaire B2B (configuration myca_partner/contact).
     */
    public function getContact(string $field): string
    {
        return (string) $this->scopeConfig->getValue('myca_partner/contact/' . $field, ScopeInterface::SCOPE_STORE);
    }

    public function getCatalogUrl(): string
    {
        return $this->url->getUrl('', ['_direct' => 'collections.html']);
    }

    /**
     * @return array<int, array{url: string, label: string, icon: string, current: bool}>
     */
    public function getNavigation(): array
    {
        $current = $this->request->getFullActionName();
        $items = [];
        foreach (self::NAVIGATION as [$path, $action, $label, $icon]) {
            $items[] = [
                'url' => $path === '__catalog__' ? $this->getCatalogUrl() : $this->url->getUrl($path),
                'label' => (string) __($label),
                'icon' => $icon,
                'current' => $action !== '' && $current === $action,
            ];
        }

        return $items;
    }

    public function getCustomerName(): string
    {
        return (string) $this->customerSession->getCustomer()->getName();
    }

    public function isOwner(): bool
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        foreach ($this->getUsers() as $user) {
            if ($user['customer_id'] === $customerId) {
                return $user['role'] === 'owner';
            }
        }

        return false;
    }

    /**
     * @return Order[]
     */
    public function getOrders(): array
    {
        return $this->getPartnerId() ? $this->partnerData->getOrders($this->getPartnerId()) : [];
    }

    /**
     * @return Invoice[]
     */
    public function getInvoices(): array
    {
        return $this->getPartnerId() ? $this->partnerData->getInvoices($this->getPartnerId()) : [];
    }

    /**
     * @return Shipment[]
     */
    public function getShipments(): array
    {
        return $this->getPartnerId() ? $this->partnerData->getShipments($this->getPartnerId()) : [];
    }

    /**
     * @return array<int, array{product_id: int, name: string, orders: int, qty: float}>
     */
    public function getTopProducts(int $limit = 3): array
    {
        return $this->getPartnerId() ? $this->partnerData->getTopProducts($this->getPartnerId(), $limit) : [];
    }

    /**
     * @return array<int, array{customer_id: int, firstname: string, lastname: string, email: string, role: string}>
     */
    public function getUsers(): array
    {
        return $this->getPartnerId() ? $this->partnerData->getUsers($this->getPartnerId()) : [];
    }

    /**
     * @return array<string, array{label: string, enabled: bool}>
     */
    public function getNotificationPreferences(): array
    {
        $values = $this->notificationPreferences->resolve(
            $this->partnerResource->getUserPreferences((int) $this->customerSession->getCustomerId())
        );
        $result = [];
        foreach ($this->notificationPreferences->getOptions() as $code => $label) {
            $result[$code] = ['label' => $label, 'enabled' => $values[$code]];
        }

        return $result;
    }

    /**
     * Commande choisie (paramètre « commande ») parmi celles du partenaire, sinon la première de la liste.
     *
     * @param Order[] $orders
     */
    public function pickOrder(array $orders, string $param = 'commande'): ?Order
    {
        $selectedId = (int) $this->request->getParam($param);
        foreach ($orders as $order) {
            if ((int) $order->getId() === $selectedId) {
                return $order;
            }
        }

        return $orders[0] ?? null;
    }

    public function getAmountExclTax(Order $order): float
    {
        return (float) $order->getGrandTotal() - (float) $order->getTaxAmount();
    }

    /**
     * Pays proposés à la livraison (configuration Magento), triés par nom.
     *
     * @return array<string, string> code => nom
     */
    public function getCountryOptions(): array
    {
        $options = [];
        foreach ($this->countryInformation->getCountriesInfo() as $country) {
            $options[(string) $country->getTwoLetterAbbreviation()] = (string) $country->getFullNameLocale();
        }
        asort($options);

        return $options;
    }

    public function formatPrice(?float $amount): string
    {
        return $amount === null ? '—' : $this->priceCurrency->format($amount, false);
    }

    /**
     * Vignette d'un produit (ligne de commande, top produits) au format de la liste produits.
     */
    public function getProductImageUrl(Product|int|null $product): string
    {
        if (is_int($product)) {
            try {
                $product = $this->productRepository->getById($product);
            } catch (NoSuchEntityException) {
                return '';
            }
            if (!$product instanceof Product) {
                return '';
            }
        }

        if (!$product instanceof Product) {
            return '';
        }
        $image = (string) $product->getData('thumbnail');
        if ($image === '' || $image === 'no_selection') {
            // Variante sans visuel : visuel du produit configurable parent.
            $parentIds = $this->configurableType->getParentIdsByChild((int) $product->getId());
            if ($parentIds) {
                try {
                    $parent = $this->productRepository->getById((int) reset($parentIds));
                    $product = $parent instanceof Product ? $parent : $product;
                } catch (NoSuchEntityException) {
                    // visuel par défaut
                }
            }
        }

        return (string) $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl();
    }

    /**
     * Initiales pour les pastilles (« JD » pour Jean Dupont).
     */
    public function initials(string $name): string
    {
        $parts = preg_split('/[\s-]+/u', trim($name)) ?: [];
        $letters = array_map(static fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)), array_filter($parts));

        return implode('', array_slice($letters, 0, 2));
    }
}
