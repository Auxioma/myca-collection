<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use Magento\Checkout\CustomerData\Cart as Subject;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class AddMaskedIdToCartData
{
    public const GUEST_MASKED_ID_KEY = 'braintree_masked_id';

    /**
     * Cart constructor
     *
     * @param Session $checkoutSession
     * @param QuoteIdToMaskedQuoteIdInterface $maskedQuote
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResourceModel $quoteIdMaskResourceModel
     */
    public function __construct(
        private readonly Session $checkoutSession,
        private readonly QuoteIdToMaskedQuoteIdInterface $maskedQuote,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly QuoteIdMaskResourceModel $quoteIdMaskResourceModel
    ) {
    }

    /**
     * Intercept getSectionData and add masked ID if available.
     *
     * @param Subject $subject
     * @param array $result
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetSectionData(
        Subject $subject,
        array $result
    ): array {
        $quote = $this->checkoutSession->getQuote();
        $quoteId = (int) $this->checkoutSession->getQuoteId();

        if ($quote &&
            $quoteId != null) {
            $maskedId = $this->maskedQuote->execute($quoteId);

            if ($maskedId === '') {
                $quoteIdMask = $this->quoteIdMaskFactory->create();
                $quoteIdMask->setQuoteId($quoteId);
                $this->quoteIdMaskResourceModel->save($quoteIdMask);

                $maskedId = $this->maskedQuote->execute($quoteId);
            }

            $result[self::GUEST_MASKED_ID_KEY] = $maskedId;
        }

        return $result;
    }
}
