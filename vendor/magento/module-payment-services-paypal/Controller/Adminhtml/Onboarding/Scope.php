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

namespace Magento\PaymentServicesPaypal\Controller\Adminhtml\Onboarding;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Phrase;
use Magento\PaymentServicesBase\Model\Config as PaymentServicesConfig;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Psr\Log\LoggerInterface;

class Scope extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Config::config';

    private const PAYPAL_SCOPE_ONBOARDING_SERVICE_PATH = '/onboarding/paypal/scope';
    private const PPCP_ONBOARDING_TYPE = 'PPCP';
    private const EXPRESS_CHECKOUT_ONBOARDING_TYPE = 'EXPRESS_CHECKOUT';
    private const SUPPORTED_PAYPAL_ONBOARDING_TYPES = [
        self::PPCP_ONBOARDING_TYPE,
        self::EXPRESS_CHECKOUT_ONBOARDING_TYPE,
    ];
    private const COUNTRY_CODE_PATTERN = '/^[A-Z]{2}$/';
    public const HTTP_STATUS_BAD_REQUEST = 400;
    public const HTTP_STATUS_BAD_GATEWAY = 502;

    /**
     * @param Context $context
     * @param ServiceClientInterface $serviceClient
     * @param ResolverInterface $localeResolver
     * @param PaymentServicesConfig $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly ServiceClientInterface $serviceClient,
        private readonly ResolverInterface $localeResolver,
        private readonly PaymentServicesConfig $config,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $websiteId = $this->getWebsiteId();
        $country = $this->getCountryCode();
        $onboardingType = $this->getOnboardingType();

        if (!$this->isValidRequest($websiteId, $country, $onboardingType)) {
            return $this->errorResult(self::HTTP_STATUS_BAD_REQUEST, __('Invalid scope onboarding request.'));
        }

        try {
            $redirect = $this->getOnboardingLink($websiteId, $country, $onboardingType);
        } catch (\Throwable $e) {
            $this->logger->error(
                'PayPal scope onboarding has failed.',
                [
                    'website_id' => $websiteId,
                    'message' => $e->getMessage(),
                ]
            );
            return $this->errorResult(
                self::HTTP_STATUS_BAD_GATEWAY,
                __('PayPal scope onboarding has failed')
            );
        }

        if ($redirect === null) {
            return $this->errorResult(
                self::HTTP_STATUS_BAD_GATEWAY,
                __('PayPal onboarding URL was not returned by the service.')
            );
        }

        return $this->successResult($redirect);
    }

    /**
     * Call SaaS to create a website-scope PayPal onboarding link
     *
     * @param int $websiteId
     * @param string $country
     * @param string $onboardingType
     * @return array|null
     */
    private function getOnboardingLink(
        int $websiteId,
        string $country,
        string $onboardingType
    ): ?array {
        $response = $this->serviceClient->request(
            $this->getServiceHeaders($websiteId),
            $this->buildScopeOnboardingServicePath($country, $onboardingType),
            Http::METHOD_POST,
            '',
            'json',
            $this->config->getEnvironmentTypeForWebsite($websiteId)
        );

        if (empty($response['is_successful'])) {
            return null;
        }

        $redirect = $response['redirect'] ?? null;
        if (!is_array($redirect)
            || !isset($redirect['url'])
            || !is_string($redirect['url'])
            || $redirect['url'] === ''
        ) {
            return null;
        }

        return $redirect;
    }

    /**
     * Get website id from the current request.
     *
     * @return int
     */
    private function getWebsiteId(): int
    {
        return (int) $this->getRequest()->getParam('websiteId');
    }

    /**
     * Get normalized country code from the current request.
     *
     * @return string
     */
    private function getCountryCode(): string
    {
        return strtoupper(trim((string) $this->getRequest()->getParam('country')));
    }

    /**
     * Get normalized PayPal onboarding type from the current request.
     *
     * @return string
     */
    private function getOnboardingType(): string
    {
        return strtoupper(trim((string) $this->getRequest()->getParam('type')));
    }

    /**
     * Validate incoming request parameters.
     *
     * @param int $websiteId
     * @param string $country
     * @param string $onboardingType
     * @return bool
     */
    private function isValidRequest(
        int $websiteId,
        string $country,
        string $onboardingType
    ): bool {
        return $websiteId > 0
            && preg_match(self::COUNTRY_CODE_PATTERN, $country) === 1
            && in_array($onboardingType, self::SUPPORTED_PAYPAL_ONBOARDING_TYPES, true);
    }

    /**
     * Build the PayPal scope onboarding service path.
     *
     * @param string $country
     * @param string $onboardingType
     * @return string
     */
    private function buildScopeOnboardingServicePath(string $country, string $onboardingType): string
    {
        return self::PAYPAL_SCOPE_ONBOARDING_SERVICE_PATH . '?' . http_build_query([
            'locale' => $this->getCurrentLocaleCode(),
            'country' => $country,
            'type' => $onboardingType,
        ]);
    }

    /**
     * Build scope headers for the PayPal scope onboarding service request.
     *
     * @param int $websiteId
     * @return array
     */
    private function getServiceHeaders(int $websiteId): array
    {
        return [
            'Content-Type' => 'application/json',
            ScopeHeadersBuilder::SCOPE_TYPE => ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
            ScopeHeadersBuilder::SCOPE_ID => (string) $websiteId,
        ];
    }

    /**
     * Get the current locale code in service-compatible format.
     *
     * @return string
     */
    private function getCurrentLocaleCode(): string
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }

    /**
     * Build a successful controller result.
     *
     * @param array $redirect
     * @return ResultInterface
     */
    private function successResult(array $redirect): ResultInterface
    {
        if ($this->isAjaxRequest()) {
            /** @var JsonResult $json */
            $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            return $json->setData(['redirect' => $redirect]);
        }

        /** @var Redirect $redirectResult */
        $redirectResult = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirectResult->setUrl($redirect['url']);
    }

    /**
     * Determine whether the response should be JSON for the modal fetch flow.
     *
     * @return bool
     */
    private function isAjaxRequest(): bool
    {
        return $this->getRequest()->isAjax() || $this->getRequest()->getParam('isAjax') === 'true';
    }

    /**
     * Build an error response.
     *
     * @param int $httpStatus
     * @param Phrase $message
     * @return ResultInterface
     */
    private function errorResult(int $httpStatus, Phrase $message): ResultInterface
    {
        /** @var Raw $raw */
        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        return $raw->setHttpResponseCode($httpStatus)->setContents($message->render());
    }
}
