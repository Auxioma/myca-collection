<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2023 Adobe
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

namespace PayPal\Braintree\Model\Recaptcha;

use Magento\Framework\Webapi\Rest\Request;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use PayPal\Braintree\Model\Ui\ConfigProvider;

class IsCaptchaApplicableForRestRequest implements IsCaptchaApplicableForRequestInterface
{
    /**
     * @param Request $request
     */
    public function __construct(
        private readonly Request $request
    ) {
    }

    /**
     * Determine whether Captcha should be used for request.
     *
     * Currently, it is only used for the REST Place order request and disables Captcha for Apple Pay as not required.
     *
     * @param EndpointInterface $endpoint
     * @return bool
     */
    public function execute(EndpointInterface $endpoint): bool
    {
        // Should check for REST API checkout place order endpoint.
        if ($endpoint->getServiceMethod() !== 'savePaymentInformationAndPlaceOrder'
            && $endpoint->getServiceMethod() !== 'placeOrder'
        ) {
            return true;
        }

        $requestData = $this->request->getRequestData();

        // Skip ReCaptcha only for Braintree sub-payment methods (Apple Pay, PayPal, Google Pay, etc.)
        // but not for the 'braintree' credit card method which requires ReCaptcha.
        // Non-Braintree methods (checkmo, cashondelivery, etc.) are left untouched
        // so the core place_order ReCaptcha continues to work for them.
        if (isset($requestData['paymentMethod']['method'])) {
            $paymentMethod = $requestData['paymentMethod']['method'];

            if ($paymentMethod !== ConfigProvider::CODE
                && str_starts_with($paymentMethod, 'braintree')
            ) {
                return false;
            }
        }

        return true;
    }
}
