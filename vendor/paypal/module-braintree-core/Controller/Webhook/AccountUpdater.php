<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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

namespace PayPal\Braintree\Controller\Webhook;

use Braintree\AccountUpdaterDailyReport;
use Braintree\WebhookNotification;
use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\Webhook\Config;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AccountUpdater implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * File name to download csv to
     */
    public const CSV_PATH = '/rtau.csv';

    /**
     * @param ResultFactory $resultFactory
     * @param Config $moduleConfig
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param BraintreeAdapter $bta
     * @param DirectoryList $directoryList
     * @param File $fileDriver
     */
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly Config $moduleConfig,
        private readonly RequestInterface $request,
        private readonly LoggerInterface $logger,
        private readonly BraintreeAdapter $bta,
        private readonly DirectoryList $directoryList,
        private readonly File $fileDriver
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_RAW);
        if (!$this->moduleConfig->isEnabled()) {
            $response->setHttpResponseCode(403);
            return $response;
        }

        $params = $this->request->getParams();
        try {
            if (!isset($params['bt_signature']) || !isset($params['bt_payload'])) {
                throw new LocalizedException(__("Request payload does not contain correct params"));
            }

            // Decode webhook and validate request signature
            $webhookResponse = WebhookNotification::parse(
                $params['bt_signature'],
                $params['bt_payload']
            );

            if ($webhookResponse->kind !== WebhookNotification::ACCOUNT_UPDATER_DAILY_REPORT) {
                throw new LocalizedException(__("Webhook kind of incorrect type"));
            }
            $accountUpdaterData = $this->getAccountUpdaterData($webhookResponse);
            $this->downloadCsvData($accountUpdaterData);

            $response->setHttpResponseCode(202);
            return $response;

        } catch (Exception $exception) {
            $this->logger->error(
                "Braintree account updater: An error occurred during processing",
                [
                    'exception_message' => $exception->getMessage(),
                    'bt_signature' => $params['bt_signature'] ?? null,
                    'bt_payload' => $params['bt_payload'] ?? null,
                ]
            );

            $response->setHttpResponseCode(400);
            return $response;
        }
    }

    /**
     * Get account updater data
     *
     * @param WebhookNotification $webhookResponse
     * @return AccountUpdaterDailyReport
     * @throws LocalizedException
     */
    private function getAccountUpdaterData(WebhookNotification $webhookResponse): AccountUpdaterDailyReport
    {
        if (!$webhookResponse instanceof WebhookNotification) {
            throw new LocalizedException(__("Webhook response data could not be parsed"));
        }
        $accountUpdaterData = isset($webhookResponse->accountUpdaterDailyReport) ?
            $webhookResponse->accountUpdaterDailyReport :
            null;
        if (!$accountUpdaterData instanceof AccountUpdaterDailyReport) {
            throw new LocalizedException(__("Webhook response data does not contain account updater daily report"));
        }

        return $accountUpdaterData;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Save CSV file to var dir for processing
     *
     * @param AccountUpdaterDailyReport $accountUpdaterData
     * @return void
     * @throws FileSystemException
     */
    public function downloadCsvData(AccountUpdaterDailyReport $accountUpdaterData): void
    {
        // URL to download
        $filePath = urldecode($accountUpdaterData->reportUrl);

        // Read csv to storage
        $csvData = $this->fileDriver->fileGetContents($filePath);
        $filePath = $this->directoryList->getPath(DirectoryList::VAR_DIR). self::CSV_PATH;
        $this->fileDriver->filePutContents($filePath, $csvData);
    }
}
