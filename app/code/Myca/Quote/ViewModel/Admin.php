<?php

declare(strict_types=1);

namespace Myca\Quote\ViewModel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Quote\Controller\Adminhtml\Quote\View as ViewController;
use Myca\Quote\Model\Quote;
use Myca\Quote\Model\QuoteStatus;
use Myca\Quote\Model\ResourceModel\Quote as QuoteResource;

/**
 * Fiche d'un devis dans l'administration.
 */
class Admin implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly QuoteResource $quoteResource,
        private readonly QuoteStatus $quoteStatus,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly UrlInterface $url
    ) {
    }

    public function getQuote(): ?Quote
    {
        $quote = $this->registry->registry(ViewController::REGISTRY_KEY);

        return $quote instanceof Quote ? $quote : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(Quote $quote): array
    {
        return $this->quoteResource->getItems((int) $quote->getId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(Quote $quote): array
    {
        return $this->quoteResource->getHistory((int) $quote->getId());
    }

    public function getStatusLabel(string $status): string
    {
        return $this->quoteStatus->getLabel($status)['label'];
    }

    public function canPropose(Quote $quote): bool
    {
        return $this->quoteStatus->canTransition($quote->getStatus(), QuoteStatus::PROPOSED, QuoteStatus::ACTOR_ADMIN);
    }

    public function canRefuse(Quote $quote): bool
    {
        return $this->quoteStatus->canTransition($quote->getStatus(), QuoteStatus::REFUSED, QuoteStatus::ACTOR_ADMIN);
    }

    public function getCustomerLabel(Quote $quote): string
    {
        try {
            $customer = $this->customerRepository->getById($quote->getCustomerId());
        } catch (NoSuchEntityException) {
            return (string) $quote->getCustomerId();
        }

        return trim($customer->getFirstname() . ' ' . $customer->getLastname()) . ' — ' . $customer->getEmail();
    }

    public function getProcessUrl(): string
    {
        return $this->url->getUrl('myca_quote/quote/process');
    }

    public function getPdfUrl(Quote $quote): string
    {
        return $this->url->getUrl('myca_quote/quote/pdf', ['id' => $quote->getId()]);
    }

    public function getBackUrl(): string
    {
        return $this->url->getUrl('myca_quote/quote/index');
    }

    public function getDefaultValidity(): string
    {
        return (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
    }
}
