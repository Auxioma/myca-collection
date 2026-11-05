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
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Phrase;
use Magento\PaymentServicesBase\Model\Config as PaymentServicesConfig;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Psr\Log\LoggerInterface;

class CountryOptions extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Config::config';

    private const ONBOARDING_COUNTRY_OPTIONS_SERVICE_PATH = '/onboarding/status';

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

        if (!$this->isValidRequest($websiteId)) {
            return $this->errorResult(
                Scope::HTTP_STATUS_BAD_REQUEST,
                __('Invalid onboarding country options request.')
            );
        }

        $environment = $this->config->getEnvironmentTypeForWebsite($websiteId);

        try {
            $response = $this->serviceClient->request(
                ['Content-Type' => 'application/json'],
                $this->buildServicePath(),
                Http::METHOD_GET,
                '',
                'json',
                $environment
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'PayPal scope onboarding country options failed.',
                [
                    'website_id' => $websiteId,
                    'environment' => $environment,
                    'message' => $exception->getMessage(),
                ]
            );

            return $this->errorResult(
                Scope::HTTP_STATUS_BAD_GATEWAY,
                __('Unable to retrieve PayPal onboarding country options.')
            );
        }

        if (empty($response['is_successful'])) {
            $this->logger->error(
                'PayPal scope onboarding country options request was unsuccessful.',
                [
                    'website_id' => $websiteId,
                    'environment' => $environment,
                    'status' => $response['status'] ?? null,
                ]
            );

            return $this->errorResult(
                Scope::HTTP_STATUS_BAD_GATEWAY,
                __('Unable to retrieve PayPal onboarding country options.')
            );
        }

        return $this->successResult($response);
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
     * Validate country options request.
     *
     * @param int $websiteId
     * @return bool
     */
    private function isValidRequest(int $websiteId): bool
    {
        return $websiteId > 0;
    }

    /**
     * Build the SaaS service path for country and payment-experience options.
     *
     * @return string
     */
    private function buildServicePath(): string
    {
        return self::ONBOARDING_COUNTRY_OPTIONS_SERVICE_PATH . '?' . http_build_query([
            'shallow' => 'true',
            'locale' => $this->getCurrentLocaleCode()
        ]);
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
     * @param array $response
     * @return ResultInterface
     */
    private function successResult(array $response): ResultInterface
    {
        unset($response['is_successful'], $response['status']);

        /** @var JsonResult $json */
        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $json->setData($response);
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
        /** @var JsonResult $json */
        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $json->setHttpResponseCode($httpStatus)->setData([
            'error' => true,
            'message' => $message->render(),
        ]);
    }
}
