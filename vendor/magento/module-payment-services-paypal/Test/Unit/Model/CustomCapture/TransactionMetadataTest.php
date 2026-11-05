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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\CustomCapture;

use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Data;
use Magento\PaymentServicesPaypal\Model\CustomCapture\TransactionMetadata;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class TransactionMetadataTest extends TestCase
{
    private const VALID_PAYLOAD = '{"version":1,"amount":30,"base_amount":24,"reason":"Increased shipping"}';
    private const CACHE_DATA_KEY = 'payment_services_custom_capture_transaction_metadata';
    private const CACHE_RESOLVED_KEY = 'payment_services_custom_capture_transaction_metadata_resolved';

    /**
     * @var TransactionMetadata
     */
    private TransactionMetadata $transactionMetadata;

    /**
     * @var TransactionRepository|MockObject
     */
    private TransactionRepository|MockObject $transactionRepository;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->transactionRepository = $this->getMockBuilder(TransactionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->transactionMetadata = new TransactionMetadata(
            new Json(),
            $this->transactionRepository,
            $this->logger
        );
    }

    public function testStageAddsVersionedJsonToCaptureTransactionMetadata(): void
    {
        $customCaptureData = new Data(30.0, 24.0, 'Increased shipping');
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setTransactionAdditionalInfo'])
            ->getMock();
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getMock();
        $order->method('getPayment')->willReturn($payment);
        $invoice->method('getOrder')->willReturn($order);
        $payment->expects($this->once())
            ->method('setTransactionAdditionalInfo')
            ->with('payment_services_custom_capture', self::VALID_PAYLOAD);

        $this->transactionMetadata->stage($invoice, $customCaptureData);
    }

    public function testExtractUsesTransientDataForNewInvoiceWithoutRepositoryLookup(): void
    {
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $invoice->method('getId')->willReturn(null);
        $invoice->setData(Data::AMOUNT, 30.0);
        $invoice->setData(Data::BASE_AMOUNT, 24.0);
        $invoice->setData(Data::REASON, 'Increased shipping');
        $this->transactionRepository->expects($this->never())->method('getByTransactionId');
        $this->logger->expects($this->never())->method('warning');

        $data = $this->transactionMetadata->extractFromInvoice($invoice);

        $this->assertNotNull($data);
        $this->assertSame(30.0, $data->getAmount());
        $this->assertSame(24.0, $data->getBaseAmount());
        $this->assertSame('Increased shipping', $data->getReason());
    }

    public function testExtractRejectsNonFiniteTransientAmount(): void
    {
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $invoice->method('getId')->willReturn(null);
        $invoice->setData(Data::AMOUNT, INF);
        $invoice->setData(Data::BASE_AMOUNT, 24.0);
        $invoice->setData(Data::REASON, 'Increased shipping');
        $this->transactionRepository->expects($this->never())->method('getByTransactionId');
        $this->logger->expects($this->never())->method('warning');

        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
    }

    public function testExtractLoadsExactCaptureTransactionOnceAndCachesResult(): void
    {
        $invoice = $this->createPersistedInvoice();
        $transaction = $this->createTransaction(self::VALID_PAYLOAD);
        $this->transactionRepository->expects($this->once())
            ->method('getByTransactionId')
            ->with('capture-id', 20, 10)
            ->willReturn($transaction);
        $this->logger->expects($this->never())->method('warning');

        $first = $this->transactionMetadata->extractFromInvoice($invoice);
        $second = $this->transactionMetadata->extractFromInvoice($invoice);

        $this->assertInstanceOf(Data::class, $first);
        $this->assertSame($first, $second);
        $this->assertSame(30.0, $first->getAmount());
        $this->assertSame(24.0, $first->getBaseAmount());
        $this->assertSame('Increased shipping', $first->getReason());
        $this->assertTrue($invoice->getData(self::CACHE_RESOLVED_KEY));
        $this->assertSame($first, $invoice->getData(self::CACHE_DATA_KEY));
    }

    public function testExtractIgnoresCommentsWhenTransactionMetadataIsMissing(): void
    {
        $invoice = $this->createPersistedInvoice(true);
        $transaction = $this->createTransaction(null);
        $this->transactionRepository->expects($this->once())
            ->method('getByTransactionId')
            ->willReturn($transaction);
        $invoice->expects($this->never())->method('getCommentsCollection');
        $this->logger->expects($this->never())->method('warning');

        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
        $this->assertTrue($invoice->getData(self::CACHE_RESOLVED_KEY));
        $this->assertNull($invoice->getData(self::CACHE_DATA_KEY));
    }

    /**
     * @dataProvider invalidPayloadProvider
     */
    public function testExtractRejectsInvalidMetadata(string $payload, string $failure): void
    {
        $invoice = $this->createPersistedInvoice();
        $transaction = $this->createTransaction($payload);
        $this->transactionRepository->expects($this->once())
            ->method('getByTransactionId')
            ->willReturn($transaction);
        $this->expectRejectionWarning($failure);

        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
        $this->assertTrue($invoice->getData(self::CACHE_RESOLVED_KEY));
        $this->assertNull($invoice->getData(self::CACHE_DATA_KEY));
    }

    public static function invalidPayloadProvider(): array
    {
        return [
            'empty payload' => ['', 'invalid_payload_type'],
            'malformed JSON' => ['{"version":1', 'malformed_json'],
            'invalid decoded payload type' => ['true', 'invalid_decoded_payload_type'],
            'unsupported version' => [
                '{"version":2,"amount":30,"base_amount":24,"reason":"Reason"}',
                'unsupported_version',
            ],
            'missing version' => ['{"amount":30,"base_amount":24,"reason":"Reason"}', 'missing_version'],
            'numeric string amount' => [
                '{"version":1,"amount":"30","base_amount":24,"reason":"Reason"}',
                'invalid_amount',
            ],
            'zero amount' => [
                '{"version":1,"amount":0,"base_amount":24,"reason":"Reason"}',
                'invalid_amount',
            ],
            'negative base amount' => [
                '{"version":1,"amount":30,"base_amount":-1,"reason":"Reason"}',
                'invalid_base_amount',
            ],
            'missing reason' => ['{"version":1,"amount":30,"base_amount":24}', 'invalid_reason'],
            'non-string reason' => [
                '{"version":1,"amount":30,"base_amount":24,"reason":[]}',
                'invalid_reason',
            ],
            'HTML-bearing reason' => [
                '{"version":1,"amount":30,"base_amount":24,"reason":"<b>Reason</b>"}',
                'invalid_reason',
            ],
            'oversized reason' => [
                '{"version":1,"amount":30,"base_amount":24,"reason":"' . str_repeat('a', 256) . '"}',
                'invalid_reason',
            ],
            'oversized multibyte reason' => [
                (string) json_encode(
                    [
                        'version' => 1,
                        'amount' => 30,
                        'base_amount' => 24,
                        'reason' => str_repeat('é', 256),
                    ],
                    JSON_UNESCAPED_UNICODE
                ),
                'invalid_reason',
            ],
        ];
    }

    public function testExtractAcceptsReasonAtMultibyteCharacterLimit(): void
    {
        $reason = str_repeat('é', 255);
        $payload = (string) json_encode(
            ['version' => 1, 'amount' => 30, 'base_amount' => 24, 'reason' => $reason],
            JSON_UNESCAPED_UNICODE
        );
        $invoice = $this->createPersistedInvoice();
        $transaction = $this->createTransaction($payload);
        $this->transactionRepository->expects($this->once())
            ->method('getByTransactionId')
            ->willReturn($transaction);
        $this->logger->expects($this->never())->method('warning');

        $data = $this->transactionMetadata->extractFromInvoice($invoice);

        $this->assertInstanceOf(Data::class, $data);
        $this->assertSame($reason, $data->getReason());
    }

    public function testExtractRejectsTransactionWithMismatchedProvenance(): void
    {
        $invoice = $this->createPersistedInvoice();
        $transaction = $this->createTransaction(self::VALID_PAYLOAD, 999);
        $this->transactionRepository->method('getByTransactionId')->willReturn($transaction);
        $this->expectRejectionWarning('provenance_mismatch');

        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
    }

    public function testExtractRejectsNonCaptureTransaction(): void
    {
        $invoice = $this->createPersistedInvoice();
        $transaction = $this->createTransaction(self::VALID_PAYLOAD, 20, Transaction::TYPE_AUTH);
        $this->transactionRepository->method('getByTransactionId')->willReturn($transaction);
        $this->expectRejectionWarning('transaction_type_mismatch');

        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
    }

    public function testExtractReturnsNullWhenSerializerRejectsMalformedPayload(): void
    {
        $serializer = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['unserialize'])
            ->getMock();
        $serializer->expects($this->once())
            ->method('unserialize')
            ->willThrowException(new InvalidArgumentException('Invalid JSON.'));
        $this->transactionMetadata = new TransactionMetadata(
            $serializer,
            $this->transactionRepository,
            $this->logger
        );
        $invoice = $this->createPersistedInvoice();
        $transaction = $this->createTransaction(self::VALID_PAYLOAD);
        $this->transactionRepository->method('getByTransactionId')->willReturn($transaction);
        $this->expectRejectionWarning('malformed_json');

        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
    }

    public function testExtractPropagatesUnexpectedSerializerFailures(): void
    {
        $serializer = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['unserialize'])
            ->getMock();
        $serializer->expects($this->once())
            ->method('unserialize')
            ->willThrowException(new RuntimeException('Unexpected serializer failure.'));
        $this->transactionMetadata = new TransactionMetadata(
            $serializer,
            $this->transactionRepository,
            $this->logger
        );
        $invoice = $this->createPersistedInvoice();
        $transaction = $this->createTransaction(self::VALID_PAYLOAD);
        $this->transactionRepository->method('getByTransactionId')->willReturn($transaction);
        $this->logger->expects($this->never())->method('warning');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected serializer failure.');
        $this->transactionMetadata->extractFromInvoice($invoice);
    }

    public function testExtractSkipsLookupWithoutCompleteTransactionIdentity(): void
    {
        $invoice = $this->createPersistedInvoice(false, '');
        $this->transactionRepository->expects($this->never())->method('getByTransactionId');
        $this->logger->expects($this->never())->method('warning');

        $this->assertNull($this->transactionMetadata->extractFromInvoice($invoice));
    }

    private function createPersistedInvoice(
        bool $mockCommentsCollection = false,
        string $transactionId = 'capture-id'
    ): Invoice|MockObject {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $payment->method('getId')->willReturn(20);
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getPayment'])
            ->getMock();
        $order->method('getId')->willReturn(10);
        $order->method('getPayment')->willReturn($payment);
        $methods = ['getId', 'getTransactionId', 'getOrder'];
        if ($mockCommentsCollection) {
            $methods[] = 'getCommentsCollection';
        }
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $invoice->method('getId')->willReturn(100);
        $invoice->method('getTransactionId')->willReturn($transactionId);
        $invoice->method('getOrder')->willReturn($order);

        return $invoice;
    }

    private function createTransaction(
        ?string $payload,
        int $paymentId = 20,
        string $transactionType = Transaction::TYPE_CAPTURE
    ): Transaction|MockObject {
        $transaction = $this->getMockBuilder(Transaction::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getTxnId',
                'getPaymentId',
                'getOrderId',
                'getTxnType',
                'getAdditionalInformation',
            ])
            ->getMock();
        $transaction->method('getTxnId')->willReturn('capture-id');
        $transaction->method('getPaymentId')->willReturn($paymentId);
        $transaction->method('getOrderId')->willReturn(10);
        $transaction->method('getTxnType')->willReturn($transactionType);
        $transaction->method('getAdditionalInformation')->willReturn(
            $payload === null ? [] : ['payment_services_custom_capture' => $payload]
        );

        return $transaction;
    }

    private function expectRejectionWarning(string $failure): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Persisted Over Capture metadata was rejected.',
                [
                    'order_id' => 10,
                    'payment_id' => 20,
                    'transaction_id' => 'capture-id',
                    'failure' => $failure,
                ]
            );
    }
}
