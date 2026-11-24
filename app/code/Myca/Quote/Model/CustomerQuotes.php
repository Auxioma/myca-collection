<?php

declare(strict_types=1);

namespace Myca\Quote\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Myca\Quote\Model\ResourceModel\Quote\CollectionFactory;

/**
 * Devis du client connecté : un client ne voit et n'agit que sur les siens.
 */
class CustomerQuotes
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $collectionFactory,
        private readonly QuoteManagement $quoteManagement
    ) {
    }

    /**
     * @return Quote[]
     */
    public function getList(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            return [];
        }
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('customer_id', ['eq' => $customerId])
            ->setOrder('quote_id', 'DESC');
        /** @var Quote[] $items */
        $items = $collection->getItems();

        return array_values($items);
    }

    public function get(int $quoteId): ?Quote
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $quote = $quoteId > 0 && $customerId > 0 ? $this->quoteManagement->load($quoteId) : null;

        return $quote !== null && $quote->getCustomerId() === $customerId ? $quote : null;
    }

    public function getCustomerId(): int
    {
        return (int) $this->customerSession->getCustomerId();
    }

    public function getAuthorName(): string
    {
        return trim((string) $this->customerSession->getCustomer()->getName());
    }
}
