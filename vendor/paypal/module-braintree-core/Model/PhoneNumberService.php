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
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PayPal\Braintree\Api\PhoneNumberServiceInterface;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Request\Data\Helper\AddressDataHelper;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class PhoneNumberService implements PhoneNumberServiceInterface
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param PhoneNumberHelper $phoneNumberHelper
     * @param Config $payPalConfig
     * @param RequestHttp $request
     * @param Session $customerSession
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteId
     * @param AddressDataHelper $addressDataHelper
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PhoneNumberHelper $phoneNumberHelper,
        private readonly Config $payPalConfig,
        private readonly RequestHttp $request,
        private readonly Session $customerSession,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteId,
        private readonly AddressDataHelper $addressDataHelper
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function execute(string $cartId): string
    {
        if ($this->payPalConfig->getContactPreference()) {
            $isLoggedIn = $this->customerSession->isLoggedIn();
            if ($this->request->getParam('fromCheckout') && $isLoggedIn) {
                $quoteId = (int) $cartId;
            } else {
                $quoteId = (int) $this->maskedQuoteId->execute($cartId);
            }

            $quote = $this->cartRepository->get($quoteId);
            $shippingAddress = $this->addressDataHelper->getShippingAddress($quote);

            $shippingData = [];
            if ($shippingAddress) {
                $shippingEmail = $quote->getShippingAddress()->getEmail();
                $countryId = $quote->getShippingAddress()->getCountryId();

                // For Guest user, Magento does not store email against Shipping Address
                // which leads to passing 'null' value for the email to the PayPal request
                // To resolve this, I have passed the email address from Billing Address
                if (!$isLoggedIn && $shippingEmail === null) {
                    $shippingData['email'] = $quote->getBillingAddress()->getEmail();
                } else {
                    $shippingData['email'] = $shippingEmail;
                }

                $countryCode = $this->phoneNumberHelper->getCountryCallingCode($countryId);
                $phoneNumber = $this->phoneNumberHelper->formatPhoneNumber($quote->getShippingAddress());
                if ($countryCode !== null && $phoneNumber) {
                    $shippingData['phoneNumber'] = [
                        'countryCode' => $countryCode,
                        'nationalNumber' => $phoneNumber
                    ];
                }
            }

            return json_encode($shippingData);
        }

        return '';
    }
}
