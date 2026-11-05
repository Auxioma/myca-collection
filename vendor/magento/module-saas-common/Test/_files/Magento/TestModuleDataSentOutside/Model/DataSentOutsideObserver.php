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

namespace Magento\TestModuleDataSentOutside\Model;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Observer that listens to a 'data_sent_outside' event.
 */
class DataSentOutsideObserver implements ObserverInterface
{
    /**
     * @var string
     */
    private const TYPE_ANYTHING = "anything";

    /**
     * @var string
     */
    private const ACTION_DATA_SENT_OUTSIDE = "data_sent_outside";

    /**
     * @var string[]
     */
    private const LOGGABLE_DATA_TYPES = ["sales", "customers", "anything"];

    /**
     * @var string
     */
    private const EVENT_INTER_SERVICE_COMM = "inter_service_communication";

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @param Json $serializer
     * @param LoggerInterface $logger
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        Json                      $serializer,
        LoggerInterface           $logger,
        ObjectManagerInterface    $objectManager
    ) {
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->objectManager = $objectManager;
    }

    /**
     * Logs data that was sent outside.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $eventData = $observer->getEvent()->getData();

        $eventData["type"] ??= self::TYPE_ANYTHING;
        $eventId = 'magento_logging_event_accessed_data';
        if (in_array($eventData["type"], self::LOGGABLE_DATA_TYPES)) {
            try {
                $eventData = [
                    "event_id" => $eventId,
                    "event_code" => self::EVENT_INTER_SERVICE_COMM,
                    "action" => self::ACTION_DATA_SENT_OUTSIDE,
                    "info" => $eventData["type"],
                    "is_success" => true,
                    "user" => $eventData["sender"] ?? "Undefined",
                    "fullaction" => $eventData["destination"] ?? "Undefined",
                    'data' => $eventData['data'] ?? []
                ];
                $this->objectManager->get(AccessedData::class)->setData(
                    [
                        "event" => $this->serializer->serialize(
                            $eventData
                        )
                    ]
                );
            } catch (\Throwable $exception) {
                $this->logger->critical(
                    __("Unable log data accessed in scope of the event!"),
                    [
                        "event_id" => $eventId,
                        "exception" => $exception->getMessage()
                    ]
                );
            }
        }
    }
}
