<?php
/************************************************************************
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
 * ************************************************************************
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\SessionFactory as CustomerSessionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Integration\Api\UserTokenIssuerInterface;
use Magento\Integration\Model\CustomUserContext;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;
use Magento\PaymentServicesBase\Model\App\ProductVersionResolver;
use Magento\PaymentServicesBase\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Payments SDK config provider.
 *
 * Provides with configuration required to initialise Payments JS SDK
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class PaymentsSDKConfigProvider
{
    private const XML_PATH_GRAPHQL_DISABLE_SESSION = 'graphql/session/disable';
    public const KEY_SDK_URL = 'paymentsSDKUrl';
    public const KEY_SDK_FALLBACK_URL = 'paymentsSDKFallbackUrl';
    public const KEY_STORE_VIEW_CODE = 'storeViewCode';
    public const KEY_OAUTH_TOKEN = 'oauthToken';
    public const KEY_GRAPHQL_ENDPOINT_URL = 'graphQLEndpointUrl';
    public const KEY_IS_GUEST_CUSTOMER = 'isGuestCustomer';
    public const KEY_COMMERCE_VERSION = 'commerceVersion';
    private const OLDEST_SUPPORTED_VERSION_OF_COMMERCE = '2.4.4';

    /**
     * @param Config $config
     * @param UserTokenIssuerInterface $tokenIssuer
     * @param UserTokenParametersFactory $tokenParametersFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerSessionFactory $customerSessionFactory
     * @param ProductVersionResolver $productVersionResolver
     * @param HttpContext $httpContext
     * @param LoggerInterface $logger
     */
    public function __construct(
        readonly Config $config,
        readonly UserTokenIssuerInterface $tokenIssuer,
        readonly UserTokenParametersFactory $tokenParametersFactory,
        readonly StoreManagerInterface $storeManager,
        readonly ScopeConfigInterface $scopeConfig,
        readonly CustomerSessionFactory $customerSessionFactory,
        readonly ProductVersionResolver $productVersionResolver,
        readonly HttpContext $httpContext,
        readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get Payments SDK params.
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentsSDKParams(): array
    {
        return [
            self::KEY_SDK_URL               => $this->config->getPaymentSDKUrl(),
            self::KEY_SDK_FALLBACK_URL      => $this->getSdkFallbackUrl(),
            self::KEY_STORE_VIEW_CODE       => $this->getStoreViewCode(),
            self::KEY_OAUTH_TOKEN           => $this->getAuthToken(),
            self::KEY_GRAPHQL_ENDPOINT_URL  => $this->getGraphQLEndpoint(),
            self::KEY_IS_GUEST_CUSTOMER     => $this->isGuestCustomer(),
            self::KEY_COMMERCE_VERSION      => $this->getCommerceVersion(),
        ];
    }

    /**
     * Returns the Payments JS SDK fallback URL.
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getSdkFallbackUrl(): string
    {
        $store = $this->storeManager->getStore();
        $queryParams = ["ext" => $this->config->getVersion()];
        return $store->getUrl("paymentservicesbase/getsdk/index", ["_query" => $queryParams]);
    }

    /**
     * Get store view code.
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getStoreViewCode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Get auth token.
     *
     * Use this token to authenticate the customer in the GraphQL request.
     *
     * @return string
     */
    private function getAuthToken(): string
    {
        if ($this->isGuestCustomer() || !$this->isCookieSessionDisabledForGQL()) {
            return '';
        }

        try {
            /** @var CustomerSession $customerSession */
            $customerSession = $this->customerSessionFactory->create();

            $userContext = new CustomUserContext(
                (int) $customerSession->getCustomerId(),
                UserContextInterface::USER_TYPE_CUSTOMER
            );

            return $this->tokenIssuer->create(
                $userContext,
                $this->tokenParametersFactory->create()
            );
        } catch (\Exception $e) {
            $this->logger->error("could not create token: " . $e->getMessage());
        }

        return '';
    }

    /**
     * Get GraphQL endpoint.
     *
     * If we use cookie session, we should use graphql endpoint that includes store code
     * If we use oauth token, we can use the default graphql endpoint
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getGraphQLEndpoint(): string
    {
        return $this->isCookieSessionDisabledForGQL()
            ? ''
            : $this->storeManager->getStore()->getBaseUrl() . 'graphql';
    }

    /**
     * Check if cookie session disabled for graphql area.
     *
     * We need this for compatibility with Magento 2.4.4.
     *
     * @return bool
     */
    private function isCookieSessionDisabledForGQL(): bool
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_GRAPHQL_DISABLE_SESSION);

        if ($value === '1') {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the current customer is a guest customer.
     *
     * @return bool
     */
    private function isGuestCustomer(): bool
    {
        return !$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    /**
     * Returns the version of Adobe Commerce/Magento Open Source in format "X.Y.Z", e.g., "2.4.7".
     *
     * Defaults to the oldest supported version of Adobe Commerce. While not ideal, this seems the
     * safest bet. The obvious alternative is the optimistic default of "latest", which may cause
     * the SDK to use APIs that do not yet exist.
     *
     * @return string version string
     */
    private function getCommerceVersion(): string
    {
        return $this->productVersionResolver->getVersion()
            ?? PaymentsSDKConfigProvider::OLDEST_SUPPORTED_VERSION_OF_COMMERCE;
    }
}
