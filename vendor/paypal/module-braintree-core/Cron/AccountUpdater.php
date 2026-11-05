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

namespace PayPal\Braintree\Cron;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use PayPal\Braintree\Api\Data\NotificationInterface;
use PayPal\Braintree\Api\Data\NotificationInterfaceFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use PayPal\Braintree\Controller\Webhook\AccountUpdater as AccountUpdaterController;
use PayPal\Braintree\Model\Webhook\Config;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AccountUpdater
{
    /**
     * @param Config $moduleConfig
     * @param PublisherInterface $publisher
     * @param NotificationInterfaceFactory $notificationFactory
     * @param LoggerInterface $logger
     * @param DirectoryList $directoryList
     * @param File $fileDriver
     */
    public function __construct(
        private readonly Config $moduleConfig,
        private readonly PublisherInterface $publisher,
        private readonly NotificationInterfaceFactory $notificationFactory,
        private readonly LoggerInterface $logger,
        private readonly DirectoryList $directoryList,
        private readonly File $fileDriver,
    ) {
    }

    /**
     * Execute cron to process CSV data
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->moduleConfig->isEnabled()) {
            return;
        }

        try {
            $parsedCsvData = $this->getCsvData();

            $this->processParsedCsvData($parsedCsvData);
        } catch (Exception $exception) {
            $this->logger->error(
                'BT-RTAU: An error occurred during CSV processing',
                ['exception_message' => $exception->getMessage()]
            );
        }
    }

    /**
     * Get CSV data of account updater
     *
     * @return array|null
     * @throws FileSystemException
     */
    public function getCsvData(): ?array
    {
        $filePath = $this->directoryList->getPath(DirectoryList::VAR_DIR) . AccountUpdaterController::CSV_PATH;

        if (!$this->fileDriver->isExists($filePath)) {
            return null;
        }

        // Open file stream and load rows
        $handle = $this->fileDriver->fileOpen($filePath, 'r');
        $rows = [];
        if ($handle) {
            try {
                while (($line = $this->fileDriver->fileReadLine($handle, 0, "\n")) != false) {
                    $rows[] = str_getcsv(string: $line, escape: "\\");
                }
                $this->fileDriver->fileClose($handle);
            } catch (\Exception $e) {
                $this->fileDriver->fileClose($handle);
            }
        }

        // Nothing to process so skip
        if (!count($rows)) {
            return null;
        }

        $header = array_shift($rows);
        $csvData = [];
        foreach ($rows as $row) {
            if (count($row) !== count($header)) {
                $this->logger->error("Braintree account updater: CSV row data does not match headers", [
                    'header' => $header,
                    'row' => $row,
                ]);
                continue;
            }
            $csvData[] = array_combine($header, $row);
        }

        $this->fileDriver->deleteFile($filePath);

        return $csvData;
    }

    /**
     * Process parsed CSV data
     *
     * @param array $parsedCsvData
     * @return void
     */
    private function processParsedCsvData(array $parsedCsvData): void
    {
        foreach ($parsedCsvData as $row) {
            try {
                /** @var NotificationInterface $notification */
                $notification = $this->notificationFactory->create();
                foreach ($row as $key => $value) {
                    $notification->setData($key, $value);
                }
                $this->publisher->publish('braintree.account.update', $notification);
            } catch (Exception $exception) {
                $this->logger->error(
                    "Braintree account updater: An error occurred whilst processing CSV row",
                    [
                        'exception_message' => $exception->getMessage(),
                        'csv_row' => $row
                    ]
                );
            }
        }
    }
}
