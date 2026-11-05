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

namespace PayPal\Braintree\Model\AccountUpdater;

use DateMalformedStringException;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\ResourceModel\PaymentToken\CollectionFactory;
use PayPal\Braintree\Api\Data\NotificationInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Consumer
{
    private const BRAINTREE_METHOD_CODE = 'braintree';

    /**
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param Diff $diff
     * @param SerializerInterface $serializer
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger,
        private readonly PaymentTokenRepositoryInterface $paymentTokenRepository,
        private readonly Diff $diff,
        private readonly SerializerInterface $serializer,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Process account updater row to update last4 and expiration if the card details have changed
     *
     * @param NotificationInterface $notification
     * @return void
     */
    public function processMessage(NotificationInterface $notification): void
    {
        try {
            $token = $this->getToken($notification);
            if (!$token instanceof PaymentTokenInterface) {
                throw new NotFoundException(__(
                    "Payment token entity with gateway_token value: %1 not found.",
                    $notification->getPaymentMethodToken()
                ));
            }
            if ($this->diff->execute($token, $notification) === false) {
                // Data does not need updating.
                return;
            }
            $this->paymentTokenRepository->save(
                $this->updateToken($token, $notification)
            );
        } catch (Exception $exception) {
            $this->logger->error("Braintree account updater: An error occurred when consuming notification", [
                'exception_message' => $exception->getMessage(),
                'payment_method_token' => $notification->getPaymentMethodToken(),
                'customer_id' => $notification->getCustomerId()
            ]);
        }
    }

    /**
     * Update token
     *
     * @param PaymentTokenInterface $token
     * @param NotificationInterface $notification
     * @return PaymentTokenInterface
     * @throws LocalizedException
     * @throws DateMalformedStringException
     */
    private function updateToken(
        PaymentTokenInterface $token,
        NotificationInterface $notification
    ): PaymentTokenInterface {
        $tokenDetails = $this->serializer->unserialize($token->getTokenDetails());
        if (!is_array($tokenDetails)) {
            throw new LocalizedException(__("Payment token details invalid"));
        }

        $cardTypeLookup = array_flip(Diff::MAGENTO_BRAINTREE_CARD_TYPE_LOOKUP);
        if (!isset($cardTypeLookup[$notification->getNewCardType()])) {
            throw new LocalizedException(__("Card type %1 not recognised", $notification->getNewCardType()));
        }

        $tokenDetails['type'] = $cardTypeLookup[$notification->getNewCardType()];
        $tokenDetails['maskedCC'] = $notification->getNewLast4();
        // Set expiration date to be start of defined month
        $newExpirationDateTime = DateTime::createFromFormat(
            'm/y/d H:i:s',
            $notification->getNewExpiration() . "/01 00:00:00"
        )->setTimezone(new DateTimeZone("UTC"));

        $newExpirationDate = $newExpirationDateTime->format("m/Y");
        $publicHashUpdateFlag = $newExpirationDate !== $tokenDetails['expirationDate'];
        $tokenDetails['expirationDate'] = $newExpirationDate;

        // Increase expiration by 1 month to ensure card expires at beginning of next month
        $newExpirationDateTime->modify("+1 month");

        $token->setTokenDetails($this->serializer->serialize($tokenDetails))
            ->setExpiresAt($newExpirationDateTime->format("Y-m-d H:i:s"));
        if ($publicHashUpdateFlag) {
            $token->setPublicHash($this->generatePublicHash($token));
        }

        return $token;
    }

    /**
     * Get token
     *
     * @param NotificationInterface $notification
     * @return PaymentTokenInterface|null
     */
    private function getToken(NotificationInterface $notification): ?PaymentTokenInterface
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(
                PaymentTokenInterface::GATEWAY_TOKEN,
                ['eq' => $notification->getPaymentMethodToken()]
            )->addFieldToFilter(
                PaymentTokenInterface::PAYMENT_METHOD_CODE,
                ['eq' => self::BRAINTREE_METHOD_CODE]
            )->setPageSize(1);
        return $collection->getSize() === 0 ? null : $collection->getFirstItem();
    }

    /**
     * Generate vault payment public hash
     *
     * @see \Magento\Vault\Observer\AfterPaymentSaveObserver::generatePublicHash
     * @param PaymentTokenInterface $paymentToken
     * @return string
     */
    protected function generatePublicHash(PaymentTokenInterface $paymentToken): string
    {
        $hashKey = $paymentToken->getGatewayToken();
        if ($paymentToken->getCustomerId()) {
            $hashKey = $paymentToken->getCustomerId();
        }

        $hashKey .= $paymentToken->getPaymentMethodCode()
            . $paymentToken->getType()
            . $paymentToken->getTokenDetails();

        return $this->encryptor->getHash($hashKey);
    }
}
