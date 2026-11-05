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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\ShippingCallback;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\Config as PaypalConfig;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\ResponseBuilder;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\ShippingProcessor;
use Magento\Quote\Api\Data\CurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ResponseBuilderTest extends TestCase
{
    /**
     * @var ScopeHeadersBuilder|MockObject
     */
    private $scopeHeaderBuilder;

    /**
     * @var OrderHelper|MockObject
     */
    private $orderHelper;

    /**
     * @var ShippingProcessor|MockObject
     */
    private $shippingProcessor;

    /**
     * @var MerchantService|MockObject
     */
    private $merchantService;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var TaxConfig|MockObject
     */
    private $taxConfig;

    /**
     * @var PaypalConfig|MockObject
     */
    private $paypalConfig;

    /**
     * @var ResponseBuilder
     */
    private ResponseBuilder $responseBuilder;

    protected function setUp(): void
    {
        $this->scopeHeaderBuilder = $this->createMock(ScopeHeadersBuilder::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->shippingProcessor = $this->createMock(ShippingProcessor::class);
        $this->merchantService = $this->createMock(MerchantService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->taxConfig = $this->createMock(TaxConfig::class);
        $this->paypalConfig = $this->createMock(PaypalConfig::class);
        $this->shippingProcessor->method('getShippingMethodCode')
            ->willReturnCallback(
                static function (string $shippingOptionId): string {
                    [$carrierCode, $methodCode] = explode('|', $shippingOptionId, 2);

                    return rawurldecode($carrierCode) . '_' . rawurldecode($methodCode);
                }
            );

        $this->responseBuilder = new ResponseBuilder(
            $this->scopeHeaderBuilder,
            $this->orderHelper,
            $this->shippingProcessor,
            $this->merchantService,
            $this->logger,
            $this->taxConfig,
            $this->paypalConfig
        );
    }

    public function testBuildResponseCollectsTaxInclusiveAmountsAndKeepsSelectedMethodTotals(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $quote = $this->createQuote('fedex_FEDEX_GROUND');
        $requestData = [
            'purchase_units' => [
                ['reference_id' => 'default']
            ]
        ];

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(true);
        $this->expectShippingTaxClass(2);
        $this->expectMerchantId();
        $this->expectOrderDetails($quote);

        $response = $this->responseBuilder->buildResponse($quote, $requestData);

        $this->assertSame($this->getExpectedResponse(), $response);
        $this->assertSame('fedex_FEDEX_GROUND', $quote->getShippingAddress()->getShippingMethod());
        $this->assertSame(10.25, $quote->getShippingAddress()->getBaseShippingAmount());
        $this->assertSame(0.85, $quote->getShippingAddress()->getBaseShippingTaxAmount());
        $this->assertSame(42.15, (float) $quote->getBaseGrandTotal());
        $this->assertSame(2, $quote->getShippingAddress()->getData('totals_collection_count'));
        $this->assertSame('fedex_FEDEX_GROUND', $quote->getData('saved_shipping_method'));
        $this->assertSame(1, $quote->getShippingAddress()->getData('shipping_rates_collection_count'));
    }

    public function testBuildResponseExcludesOptionWithoutAQuoteShippingRate(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $quote = $this->createQuote(
            'fedex_FEDEX_GROUND',
            [
                'fedex_FEDEX_GROUND' => [10.25, 0.85, 42.15]
            ]
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(true);
        $this->expectShippingTaxClass(2);
        $this->expectMerchantId();
        $this->expectOrderDetails($quote, 2);

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );
        $expectedResponse = $this->getExpectedResponse();
        array_shift($expectedResponse['purchase_units'][0]['shipping_options']);

        $this->assertSame($expectedResponse, $response);
        $this->assertSame('fedex_FEDEX_GROUND', $quote->getShippingAddress()->getShippingMethod());
        $this->assertSame(1, $quote->getShippingAddress()->getData('totals_collection_count'));
    }

    public function testBuildResponsePreservesSelectedOptionWithoutARefreshedQuoteShippingRate(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $quote = $this->createQuote(
            'fedex_FEDEX_GROUND',
            null,
            null,
            null,
            42.15,
            ['flatrate_flatrate']
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(true);
        $this->expectShippingTaxClass(2);
        $this->expectMerchantId();
        $this->expectOrderDetails($quote);

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );
        $options = $response['purchase_units'][0]['shipping_options'];

        $this->assertSame($this->getExpectedResponse(), $response);
        $this->assertCount(2, $options);
        $this->assertCount(2, array_unique(array_column($options, 'id')));
        $this->assertTrue($options[1]['selected']);
        $this->assertSame('fedex_FEDEX_GROUND', $quote->getShippingAddress()->getShippingMethod());
        $this->assertSame(2, $quote->getShippingAddress()->getData('totals_collection_count'));
    }

    public function testBuildResponsePreservesSelectedOptionWhenAllAlternativeRatesAreUnavailable(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $quote = $this->createQuote(
            'fedex_FEDEX_GROUND',
            null,
            null,
            null,
            42.15,
            []
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(true);
        $this->expectShippingTaxClass(2);
        $this->expectMerchantId();
        $this->expectOrderDetails($quote, 2);

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );
        $options = $response['purchase_units'][0]['shipping_options'];

        $this->assertCount(1, $options);
        $this->assertSame('fedex|FEDEX_GROUND', $options[0]['id']);
        $this->assertTrue($options[0]['selected']);
        $this->assertSame('11.10', $options[0]['amount']['value']);
        $this->assertSame('fedex_FEDEX_GROUND', $quote->getShippingAddress()->getShippingMethod());
        $this->assertSame(1, $quote->getShippingAddress()->getData('totals_collection_count'));
    }

    public function testBuildResponseRestoresSelectedMethodWhenOptionCollectionFails(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $exception = new Exception('Unable to collect shipping totals');
        $quote = $this->createQuote(
            'fedex_FEDEX_GROUND',
            null,
            'flatrate_flatrate',
            $exception
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(true);
        $this->expectShippingTaxClass(2);
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->identicalTo($exception));

        try {
            $this->responseBuilder->buildResponse(
                $quote,
                ['purchase_units' => [['reference_id' => 'default']]]
            );
            $this->fail('A LocalizedException was expected.');
        } catch (LocalizedException $localizedException) {
            $this->assertSame('Unable to collect shipping totals', $localizedException->getMessage());
        }

        $shippingAddress = $quote->getShippingAddress();
        $this->assertSame('fedex_FEDEX_GROUND', $shippingAddress->getShippingMethod());
        $this->assertSame(1, $shippingAddress->getData('totals_collection_count'));
        $this->assertSame(0, (int) $quote->getData('save_count'));
    }

    public function testBuildResponseCollectsOnlySelectedMethodWhenAllMethodTaxCalculationIsDisabled(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $quote = $this->createQuote('fedex_FEDEX_GROUND');

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(false);
        $this->taxConfig->expects($this->never())
            ->method('getShippingTaxClass');
        $this->expectMerchantId();
        $this->expectOrderDetails($quote);

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );

        $this->assertSame(
            $this->getExpectedResponse('5.00'),
            $response
        );
        $this->assertSame('fedex_FEDEX_GROUND', $quote->getShippingAddress()->getShippingMethod());
        $this->assertSame(1, $quote->getShippingAddress()->getData('totals_collection_count'));
        $this->assertNull($quote->getShippingAddress()->getData('shipping_rates_collection_count'));
    }

    public function testBuildResponseUsesCollectedZeroShippingAmount(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $quote = $this->createQuote(
            'fedex_FEDEX_GROUND',
            ['fedex_FEDEX_GROUND' => [0.00, 0.00, 31.05]],
            null,
            null,
            31.05
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(false);
        $this->taxConfig->expects($this->never())
            ->method('getShippingTaxClass');
        $this->expectMerchantId();
        $this->expectOrderDetails($quote, 3, '0.00');

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );

        $this->assertSame(
            $this->getExpectedResponse('5.00', '0.00', '31.05', '0.00'),
            $response
        );
        $this->assertSame(1, $quote->getShippingAddress()->getData('totals_collection_count'));
    }

    public function testBuildResponseCollectsOnlySelectedMethodWhenShippingTaxIsDisabled(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $quote = $this->createQuote(
            'fedex_FEDEX_GROUND',
            [
                'flatrate_flatrate' => [5.00, 0.00, 36.05],
                'fedex_FEDEX_GROUND' => [10.25, 0.00, 41.30]
            ],
            null,
            null,
            41.30
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(true);
        $this->expectShippingTaxClass(0);
        $this->expectMerchantId();
        $this->expectOrderDetails($quote, 3, '10.25');

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );

        $this->assertSame(
            $this->getExpectedResponse('5.00', '10.25', '41.30', '10.25'),
            $response
        );
        $this->assertSame('fedex_FEDEX_GROUND', $quote->getShippingAddress()->getShippingMethod());
        $this->assertSame(1, $quote->getShippingAddress()->getData('totals_collection_count'));
    }

    public function testBuildResponsePreservesCollectedShippingTaxRounding(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $quote = $this->createQuote(
            'fedex_FEDEX_GROUND',
            [
                'flatrate_flatrate' => [5.00, 0.41, 36.46],
                'fedex_FEDEX_GROUND' => [15.00, 1.23, 47.28]
            ],
            null,
            null,
            47.28
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(false);
        $this->taxConfig->expects($this->never())
            ->method('getShippingTaxClass');
        $this->expectMerchantId();
        $this->expectOrderDetails($quote, 3, '16.23');

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );

        $purchaseUnit = $response['purchase_units'][0];
        $this->assertSame('16.23', $purchaseUnit['shipping_options'][1]['amount']['value']);
        $this->assertSame('16.23', $purchaseUnit['amount']['breakdown']['shipping']['value']);
    }

    public function testBuildResponseDoesNotSaveWhenFinalSelectedCollectionFails(): void
    {
        $shippingMethods = $this->getShippingMethods();
        $exception = new Exception('Unable to collect selected shipping totals');
        $quote = $this->createQuote(
            'fedex_FEDEX_GROUND',
            null,
            'fedex_FEDEX_GROUND',
            $exception
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(true);
        $this->expectShippingTaxClass(2);
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->identicalTo($exception));

        try {
            $this->responseBuilder->buildResponse(
                $quote,
                ['purchase_units' => [['reference_id' => 'default']]]
            );
            $this->fail('A LocalizedException was expected.');
        } catch (LocalizedException $localizedException) {
            $this->assertSame('Unable to collect selected shipping totals', $localizedException->getMessage());
        }

        $this->assertSame('fedex_FEDEX_GROUND', $quote->getShippingAddress()->getShippingMethod());
        $this->assertSame(0, (int) $quote->getData('save_count'));
    }

    public function testBuildResponseLimitsMethodsAndPreservesSelectedMethodOutsideLimit(): void
    {
        $shippingMethods = $this->getShippingMethods(12, 11);
        $shippingAmounts = [];
        for ($index = 0; $index < 9; $index++) {
            $shippingAmounts['carrier' . $index . '_method' . $index] = [5.00 + $index, 0.50, 35.50 + $index];
        }
        $shippingAmounts['carrier11_method11'] = [20.00, 2.00, 42.15];
        $quote = $this->createQuote('carrier11_method11', $shippingAmounts);

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(true);
        $this->expectShippingTaxClass(2);
        $this->expectMerchantId();
        $this->expectOrderDetails($quote, 11, '22.00');

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );
        $options = $response['purchase_units'][0]['shipping_options'];

        $this->assertCount(10, $options);
        $this->assertSame(
            [
                'carrier0|method0',
                'carrier1|method1',
                'carrier2|method2',
                'carrier3|method3',
                'carrier4|method4',
                'carrier5|method5',
                'carrier6|method6',
                'carrier7|method7',
                'carrier8|method8',
                'carrier11|method11'
            ],
            array_column($options, 'id')
        );
        $this->assertTrue($options[9]['selected']);
        $this->assertNotContains('carrier9|method9', array_column($options, 'id'));
        $this->assertNotContains('carrier10|method10', array_column($options, 'id'));
        $this->assertSame(range(0, 9), array_keys($options));
        $this->assertSame('22.00', $options[9]['amount']['value']);
        $this->assertSame(10, $quote->getShippingAddress()->getData('totals_collection_count'));
        $this->assertSame('carrier11_method11', $quote->getShippingAddress()->getShippingMethod());
    }

    public function testBuildResponseReturnsFirstTenMethodsWhenSelectedMethodIsWithinLimit(): void
    {
        $shippingMethods = $this->getShippingMethods(12, 8);
        $quote = $this->createQuote(
            'carrier8_method8',
            ['carrier8_method8' => [13.00, 1.08, 42.15]]
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(false);
        $this->taxConfig->expects($this->never())
            ->method('getShippingTaxClass');
        $this->expectMerchantId();
        $this->expectOrderDetails($quote, 11, '14.08');

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );
        $options = $response['purchase_units'][0]['shipping_options'];

        $this->assertCount(10, $options);
        $this->assertSame('carrier9|method9', $options[9]['id']);
        $this->assertTrue($options[8]['selected']);
        $this->assertSame('14.08', $options[8]['amount']['value']);
        $this->assertSame(1, $quote->getShippingAddress()->getData('totals_collection_count'));
    }

    public function testBuildResponseLimitsMethodsBeforeAssigningDefaultMethod(): void
    {
        $shippingMethods = $this->getShippingMethods(12);
        $quote = $this->createQuote(
            null,
            ['carrier0_method0' => [5.00, 0.41, 42.15]]
        );

        $this->expectShippingMethods($quote, $shippingMethods);
        $this->expectCalculateTaxForAllShippingMethods(false);
        $this->taxConfig->expects($this->never())
            ->method('getShippingTaxClass');
        $this->expectMerchantId();
        $this->expectOrderDetails($quote, 11, '5.41');

        $response = $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );
        $options = $response['purchase_units'][0]['shipping_options'];

        $this->assertCount(10, $options);
        $this->assertTrue($options[0]['selected']);
        $this->assertSame('5.41', $options[0]['amount']['value']);
        $this->assertSame('carrier0_method0', $quote->getShippingAddress()->getShippingMethod());
        $this->assertSame(1, $quote->getShippingAddress()->getData('totals_collection_count'));
    }

    public function testBuildResponseLogsAndWrapsException(): void
    {
        $quote = $this->createQuote(null);
        $exception = new Exception('Unable to estimate shipping');

        $this->shippingProcessor->expects($this->once())
            ->method('getShippingMethods')
            ->willThrowException($exception);
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->identicalTo($exception));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to estimate shipping');

        $this->responseBuilder->buildResponse(
            $quote,
            ['purchase_units' => [['reference_id' => 'default']]]
        );
    }

    private function createQuote(
        ?string $selectedShippingMethod,
        ?array $shippingAmounts = null,
        ?string $failingShippingMethod = null,
        ?Exception $collectionException = null,
        float $expectedGrandTotal = 42.15,
        ?array $availableShippingMethodCodes = null
    ): Quote {
        $shippingAmounts ??= [
            'flatrate_flatrate' => [5.00, 0.41, 36.46],
            'fedex_FEDEX_GROUND' => [10.25, 0.85, 42.15]
        ];
        $shippingAddress = $this->createAddress($availableShippingMethodCodes ?? array_keys($shippingAmounts));
        $shippingAddress->setShippingMethod($selectedShippingMethod);

        $payment = $this->createMock(InfoInterface::class);
        $payment->expects($this->any())
            ->method('setAdditionalInformation')
            ->with('paypal_order_amount', $expectedGrandTotal)
            ->willReturnSelf();

        $currency = $this->createMock(CurrencyInterface::class);
        $currency->method('getBaseCurrencyCode')
            ->willReturn('USD');

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getShippingAddress',
                'collectTotals',
                'getPayment',
                'save',
                'getStore',
                'getCurrency',
                'getReservedOrderId'
            ])
            ->getMock();
        $quote->setData([
            'base_grand_total' => $expectedGrandTotal,
            'store_id' => 1
        ]);
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->method('collectTotals')
            ->willReturnCallback(
                static function () use (
                    $quote,
                    $shippingAddress,
                    $shippingAmounts,
                    $failingShippingMethod,
                    $collectionException
                ): void {
                    $shippingMethod = $shippingAddress->getShippingMethod();
                    $shippingAddress->setData(
                        'totals_collection_count',
                        (int) $shippingAddress->getData('totals_collection_count') + 1
                    );

                    if ($shippingMethod === $failingShippingMethod && $collectionException !== null) {
                        throw $collectionException;
                    }

                    if (isset($shippingAmounts[$shippingMethod])) {
                        [$shippingAmount, $shippingTaxAmount, $grandTotal] = $shippingAmounts[$shippingMethod];
                        $shippingAddress->setBaseShippingAmount($shippingAmount);
                        $shippingAddress->setBaseShippingTaxAmount($shippingTaxAmount);
                        $quote->setData('base_grand_total', $grandTotal);
                    }
                }
            );
        $quote->method('getPayment')
            ->willReturn($payment);
        $quote->method('save')
            ->willReturnCallback(
                static function () use ($quote, $shippingAddress): Quote {
                    $quote->setData('save_count', (int) $quote->getData('save_count') + 1);
                    $quote->setData('saved_shipping_method', $shippingAddress->getShippingMethod());
                    $quote->setData('saved_base_shipping_amount', $shippingAddress->getBaseShippingAmount());
                    $quote->setData('saved_base_shipping_tax_amount', $shippingAddress->getBaseShippingTaxAmount());

                    return $quote;
                }
            );
        $quote->method('getStore')
            ->willReturn($this->createMock(StoreInterface::class));
        $quote->method('getCurrency')
            ->willReturn($currency);
        $quote->method('getReservedOrderId')
            ->willReturn('100000001');

        return $quote;
    }

    /**
     * Expect shipping methods
     *
     * @param Quote $quote
     * @param array $shippingMethods
     * @return void
     */
    private function expectShippingMethods(Quote $quote, array $shippingMethods): void
    {
        $this->shippingProcessor->expects($this->once())
            ->method('getShippingMethods')
            ->with($this->identicalTo($quote))
            ->willReturn($shippingMethods);
        $this->shippingProcessor->expects($this->once())
            ->method('setDefaultShippingMethod')
            ->with($this->identicalTo($quote), $this->isType('array'))
            ->willReturnCallback(
                static function (Quote $quote, array $methods): array {
                    if (!$quote->getShippingAddress()->getShippingMethod() && !empty($methods)) {
                        [$carrierCode, $methodCode] = explode('|', $methods[0]['id'], 2);
                        $methods[0]['selected'] = true;
                        $quote->getShippingAddress()->setShippingMethod($carrierCode . '_' . $methodCode);
                    }

                    return $methods;
                }
            );
    }

    /**
     * Expect tax calculation configuration
     *
     * @param bool $enabled
     * @return void
     */
    private function expectCalculateTaxForAllShippingMethods(bool $enabled): void
    {
        $this->paypalConfig->expects($this->once())
            ->method('isCalculateTaxForAllShippingMethodsEnabled')
            ->with(1)
            ->willReturn($enabled);
    }

    /**
     * Expect shipping tax class
     *
     * @param int $shippingTaxClass
     * @return void
     */
    private function expectShippingTaxClass(int $shippingTaxClass): void
    {
        $this->taxConfig->expects($this->once())
            ->method('getShippingTaxClass')
            ->willReturn($shippingTaxClass);
    }

    /**
     * Expect Merchant ID
     *
     * @return void
     */
    private function expectMerchantId(): void
    {
        $this->scopeHeaderBuilder->expects($this->once())
            ->method('buildScopeHeaders')
            ->willReturn([
                'x-scope-type' => 'website',
                'x-scope-id' => '2'
            ]);
        $this->merchantService->expects($this->once())
            ->method('getMerchantAndPartnerInformation')
            ->with('website', 2)
            ->willReturn(['merchantIdentifier' => 'merchant-id']);
    }

    /**
     * Expect order details
     *
     * @param Quote $quote
     * @return void
     */
    private function expectOrderDetails(
        Quote $quote,
        int $formatAmountCalls = 3,
        string $shippingAmount = '11.10'
    ): void {
        $this->orderHelper->expects($this->exactly($formatAmountCalls))
            ->method('formatAmount')
            ->willReturnCallback(
                static fn (float $amount): string => number_format($amount, 2, '.', '')
            );
        $this->orderHelper->expects($this->once())
            ->method('getAmountBreakdown')
            ->with($this->identicalTo($quote), '100000001')
            ->willReturn([
                'item_total' => ['value' => '31.05', 'currency_code' => 'USD'],
                'shipping' => ['value' => $shippingAmount, 'currency_code' => 'USD']
            ]);
        $this->orderHelper->expects($this->once())
            ->method('getLineItems')
            ->with($this->identicalTo($quote), '100000001')
            ->willReturn([['name' => 'Test Product']]);
    }

    /**
     * Get shipping methods
     *
     * @return array
     */
    private function getShippingMethods(int $methodCount = 2, ?int $selectedMethodIndex = null): array
    {
        if ($methodCount !== 2) {
            $shippingMethods = [];
            for ($index = 0; $index < $methodCount; $index++) {
                $shippingMethods[] = [
                    'id' => 'carrier' . $index . '|method' . $index,
                    'label' => 'Shipping Method ' . $index,
                    'type' => 'SHIPPING',
                    'selected' => $selectedMethodIndex === $index,
                    'amount' => ['value' => 5.00 + $index, 'currency_code' => 'USD']
                ];
            }

            return $shippingMethods;
        }

        return [
            [
                'id' => 'flatrate|flatrate',
                'label' => 'Flat Rate',
                'type' => 'SHIPPING',
                'selected' => false,
                'amount' => ['value' => 5.00, 'currency_code' => 'USD']
            ],
            [
                'id' => 'fedex|FEDEX_GROUND',
                'label' => 'FedEx - Ground',
                'type' => 'SHIPPING',
                'selected' => true,
                'amount' => ['value' => 10.25, 'currency_code' => 'USD']
            ]
        ];
    }

    /**
     * Get expected response
     *
     * @return array
     */
    private function getExpectedResponse(
        float|string $flatRateAmount = '5.41',
        float|string $fedexAmount = '11.10',
        string $grandTotal = '42.15',
        string $shippingAmount = '11.10'
    ): array {
        return [
            'id' => 'merchant-id',
            'purchase_units' => [
                [
                    'reference_id' => 'default',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => $grandTotal,
                        'breakdown' => [
                            'item_total' => ['value' => '31.05', 'currency_code' => 'USD'],
                            'shipping' => ['value' => $shippingAmount, 'currency_code' => 'USD']
                        ]
                    ],
                    'items' => [['name' => 'Test Product']],
                    'shipping_options' => [
                        [
                            'id' => 'flatrate|flatrate',
                            'label' => 'Flat Rate',
                            'type' => 'SHIPPING',
                            'selected' => false,
                            'amount' => ['value' => $flatRateAmount, 'currency_code' => 'USD']
                        ],
                        [
                            'id' => 'fedex|FEDEX_GROUND',
                            'label' => 'FedEx - Ground',
                            'type' => 'SHIPPING',
                            'selected' => true,
                            'amount' => ['value' => $fedexAmount, 'currency_code' => 'USD']
                        ]
                    ]
                ]
            ]
        ];
    }

    private function createAddress(array $shippingMethodCodes): Address
    {
        $shippingRates = [];
        foreach ($shippingMethodCodes as $shippingMethodCode) {
            $shippingRates[] = new class ($shippingMethodCode) {
                /**
                 * @param string $shippingMethodCode
                 */
                public function __construct(private readonly string $shippingMethodCode)
                {
                }

                /**
                 * @return string
                 */
                public function getCode(): string
                {
                    return $this->shippingMethodCode;
                }
            };
        }

        $shippingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collectShippingRates', 'getAllShippingRates'])
            ->getMock();
        $shippingAddress->method('collectShippingRates')
            ->willReturnCallback(
                static function () use ($shippingAddress): Address {
                    $shippingAddress->setData(
                        'shipping_rates_collection_count',
                        (int) $shippingAddress->getData('shipping_rates_collection_count') + 1
                    );

                    return $shippingAddress;
                }
            );
        $shippingAddress->method('getAllShippingRates')->willReturn($shippingRates);

        return $shippingAddress;
    }
}
