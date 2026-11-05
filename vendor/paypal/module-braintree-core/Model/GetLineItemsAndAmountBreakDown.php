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

namespace PayPal\Braintree\Model;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PayPal\Braintree\Api\GetLineItemsAndAmountBreakDownInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use PayPal\Braintree\Model\Request\Data\Helper\LineItems;
use PayPal\Braintree\Model\Request\Data\Helper\Totals;
use PayPal\Braintree\Model\Request\Data\Helper\MaskedQuoteIdHelper;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class GetLineItemsAndAmountBreakDown implements GetLineItemsAndAmountBreakDownInterface
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param LineItems $lineItems
     * @param Totals $totals
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteId
     * @param RequestHttp $request
     * @param Session $customerSession
     * @param MaskedQuoteIdHelper $maskedQuoteIdHelper
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LineItems $lineItems,
        private readonly Totals $totals,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteId,
        private readonly RequestHttp $request,
        private readonly Session $customerSession,
        private readonly MaskedQuoteIdHelper $maskedQuoteIdHelper
    ) {
    }

    /**
     * Rest endpoint to calculate totals and line items
     *
     * @param string $method
     * @param string $cartId
     * @return string
     * @throws LocalizedException
     */
    public function execute(string $method, string $cartId): string
    {
        if ($this->request->getParam('fromCheckout') && $this->customerSession->isLoggedIn()) {
            $quoteId = (int) $cartId;
            $cartId = $this->maskedQuoteIdHelper->fetchAndEnsureQuoteMaskIdExist($quoteId);
        } else {
            $quoteId = (int) $this->maskedQuoteId->execute($cartId);
        }
        $quote = $this->cartRepository->get($quoteId);
        $lineItems = $this->lineItems->getLineItems($quote);

        $data = [
            'cartId' => $cartId,
            'amount' => $quote->getBaseGrandTotal(),
            'lineItems' => $lineItems,
            'amountBreakdown' => [
                'itemTotal' => $this->totals->getItemsTotal($quote),
                'taxTotal' => $this->totals->getTaxAmount($quote),
                'discount' => $this->totals->getDiscountAmount($quote),
                'shipping' => $this->totals->getShippingAmount($quote)
            ]
        ];

        return json_encode($data);
    }
}
