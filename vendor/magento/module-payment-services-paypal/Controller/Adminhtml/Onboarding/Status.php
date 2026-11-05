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
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Phrase;
use Magento\PaymentServicesBase\Model\Config as PaymentServicesConfig;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantInterface;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use Psr\Log\LoggerInterface;
use Throwable;

class Status extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Config::config';

    /**
     * @param Context $context
     * @param MerchantService $merchantService
     * @param PaypalMerchantResolver $paypalMerchantResolver
     * @param PaymentServicesConfig $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly MerchantService $merchantService,
        private readonly PaypalMerchantResolver $paypalMerchantResolver,
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
                __('Invalid onboarding status request.')
            );
        }

        $environment = $this->config->getEnvironmentTypeForWebsite($websiteId);

        try {
            $scope = $this->paypalMerchantResolver->getPaypalMerchantForExactScope(
                $this->merchantService->getAllScopesForMerchant(
                    $environment,
                    $this->isForceRefreshRequested()
                ),
                $websiteId,
                ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE
            );
        } catch (Throwable $e) {
            $this->logger->error(
                'PayPal scope onboarding status failed:',
                [
                    'website_id' => $websiteId,
                    'environment' => $environment,
                    'message' => $e->getMessage()
                ]
            );
            return $this->errorResult(
                Scope::HTTP_STATUS_BAD_GATEWAY,
                __('Unable to retrieve PayPal onboarding status.')
            );
        }

        return $this->successResult($websiteId, $scope);
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
     * Determine whether the merchant scopes should be refreshed from SaaS.
     *
     * @return bool
     */
    private function isForceRefreshRequested(): bool
    {
        $forceRefresh = $this->getRequest()->getParam('forceRefresh');

        return $forceRefresh === true
            || $forceRefresh === 1
            || $forceRefresh === '1'
            || $forceRefresh === 'true';
    }

    /**
     * Validate incoming request parameters.
     *
     * @param int $websiteId
     * @return bool
     */
    private function isValidRequest(int $websiteId): bool
    {
        return $websiteId > 0;
    }

    /**
     * Build a successful onboarding status result.
     *
     * @param int $websiteId
     * @param PaypalMerchantInterface|null $scope
     * @return ResultInterface
     */
    private function successResult(int $websiteId, ?PaypalMerchantInterface $scope): ResultInterface
    {
        $payPalMerchantId = $scope?->getId() ?? null;
        $status = $scope?->getStatus() ?? null;

        /** @var JsonResult $json */
        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $json->setData([
            'scopeType' => ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
            'scopeId' => $websiteId,
            'paypalMerchantId' => $payPalMerchantId,
            'status' => $status,
            'isActive' => $this->isActivePayPalMerchant($payPalMerchantId, $status),
        ]);
    }

    /**
     * Check whether the PayPal merchant is active.
     *
     * @param string|null $paypalMerchantId
     * @param string|null $status
     * @return bool
     */
    private function isActivePayPalMerchant(?string $paypalMerchantId, ?string $status): bool
    {
        return $paypalMerchantId !== null
            && $status === PaypalMerchantInterface::COMPLETED_STATUS;
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
