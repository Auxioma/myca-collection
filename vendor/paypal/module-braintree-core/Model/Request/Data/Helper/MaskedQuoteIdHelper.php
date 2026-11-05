<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
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

namespace PayPal\Braintree\Model\Request\Data\Helper;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;

class MaskedQuoteIdHelper
{
    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResourceModel $quoteIdMaskResourceModel
     * @param QuoteIdToMaskedQuoteIdInterface $maskedQuoteId
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly QuoteIdMaskResourceModel $quoteIdMaskResourceModel,
        private readonly QuoteIdToMaskedQuoteIdInterface $maskedQuoteId,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * Create masked id for the active quote if it's not exists
     *
     * @param int $quoteId
     * @return string
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    public function fetchAndEnsureQuoteMaskIdExist(int $quoteId): string
    {
        try {
            $maskedId = $this->maskedQuoteId->execute($quoteId);
        } catch (NoSuchEntityException $e) {
            $maskedId = '';
        }

        if ($maskedId === '') {
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $quoteIdMask->setQuoteId($quoteId);
            $this->quoteIdMaskResourceModel->save($quoteIdMask);

            $maskedId = $this->maskedQuoteId->execute($quoteId);
        }

        return $maskedId;
    }

    /**
     * Get quote by masked Id
     *
     * @param string $maskedId
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuoteByMaskedId(string $maskedId): CartInterface
    {
        $maskedIdObject = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResourceModel->load($maskedIdObject, $maskedId, 'masked_id');
        return $this->cartRepository->get((int) $maskedIdObject->getQuoteId());
    }
}
