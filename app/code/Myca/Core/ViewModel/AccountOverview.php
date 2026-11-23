<?php

declare(strict_types=1);

namespace Myca\Core\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface as OrderCollectionFactory;
use Magento\Wishlist\Model\Item as WishlistItem;
use Magento\Wishlist\Model\WishlistFactory;

/**
 * Données de l'espace client (M28, M29) pour le client connecté : commandes, favoris, avis, statut.
 */
class AccountOverview implements ArgumentInterface
{
    /** @var Order[]|null */
    private ?array $orders = null;

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly OrderConfig $orderConfig,
        private readonly WishlistFactory $wishlistFactory,
        private readonly ReviewCollectionFactory $reviewCollectionFactory,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly OrderProgress $orderProgress,
        private readonly ImageHelper $imageHelper
    ) {
    }

    public function getCustomerName(): string
    {
        return (string) $this->customerSession->getCustomer()->getName();
    }

    public function getCustomerEmail(): string
    {
        return (string) $this->customerSession->getCustomer()->getEmail();
    }

    /**
     * Libellé du groupe client (ex. « Client Privilège »), affiché comme statut.
     */
    public function getGroupLabel(): string
    {
        try {
            return (string) $this->groupRepository->getById((int) $this->customerSession->getCustomerGroupId())->getCode();
        } catch (LocalizedException) {
            return '';
        }
    }

    /**
     * Commandes visibles en vitrine, de la plus récente à la plus ancienne.
     *
     * @return Order[]
     */
    public function getOrders(): array
    {
        if ($this->orders === null) {
            /** @var Order[] $items */
            $items = $this->orderCollectionFactory->create((int) $this->customerSession->getCustomerId())
                ->addFieldToFilter('status', ['in' => $this->orderConfig->getVisibleOnFrontStatuses()])
                ->setOrder('created_at', 'desc')
                ->getItems();
            $this->orders = array_values($items);
        }

        return $this->orders;
    }

    /**
     * @return Order[]
     */
    public function getRecentOrders(int $days = 30, int $limit = 5): array
    {
        $since = strtotime('-' . $days . ' days');

        return array_slice(
            array_values(array_filter($this->getOrders(), static fn (Order $order) => strtotime((string) $order->getCreatedAt()) >= $since)),
            0,
            $limit
        );
    }

    public function getLastOrder(): ?Order
    {
        return $this->getOrders()[0] ?? null;
    }

    public function countOrdersInProgress(): int
    {
        return count(array_filter($this->getOrders(), fn (Order $order) => $this->orderProgress->isInProgress($order)));
    }

    /**
     * @return WishlistItem[]
     */
    public function getWishlistItems(int $limit = 4): array
    {
        $wishlist = $this->wishlistFactory->create()->loadByCustomerId((int) $this->customerSession->getCustomerId());
        if (!$wishlist->getId()) {
            return [];
        }
        /** @var WishlistItem[] $items */
        $items = $wishlist->getItemCollection()->setOrder('added_at', 'desc')->setPageSize($limit)->getItems();

        return array_values($items);
    }

    public function countWishlistItems(): int
    {
        $wishlist = $this->wishlistFactory->create()->loadByCustomerId((int) $this->customerSession->getCustomerId());

        return $wishlist->getId() ? (int) $wishlist->getItemsCount() : 0;
    }

    public function countReviews(): int
    {
        return $this->reviewCollectionFactory->create()
            ->addCustomerFilter((int) $this->customerSession->getCustomerId())
            ->getSize();
    }

    /**
     * Vignette d'un produit (commande, favori) au format de la liste produits.
     */
    public function getImageUrl(?Product $product): string
    {
        return $product ? (string) $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl() : '';
    }

    /**
     * Premier article (ligne parente) d'une commande, pour l'aperçu « Ma dernière commande ».
     */
    public function getFirstItem(Order $order): ?Order\Item
    {
        foreach ($order->getAllVisibleItems() as $item) {
            return $item;
        }

        return null;
    }
}
