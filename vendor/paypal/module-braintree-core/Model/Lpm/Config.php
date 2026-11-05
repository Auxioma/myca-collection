<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
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

namespace PayPal\Braintree\Model\Lpm;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\StoreConfigResolver;

/**
 * Provide configuration for LPMs
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const KEY_ACTIVE = 'active';
    public const KEY_ALLOWED_METHODS = 'allowed_methods';
    public const KEY_TITLE = 'title';
    public const KEY_FALLBACK_BUTTON_TEXT = 'fallback_button_text';
    public const KEY_REDIRECT_ON_FAIL = 'redirect_on_fail';
    private const LPM_FALLBACK_ACTION_URL = 'braintree/lpm/fallback';

    public const VALUE_BANCONTACT = 'bancontact';
    public const VALUE_EPS = 'eps';
    public const VALUE_IDEAL = 'ideal';
    public const VALUE_MYBANK = 'mybank';
    public const VALUE_P24 = 'p24';
    public const VALUE_SEPA = 'sepa';
    public const VALUE_PAY_UPON_INVOICE = 'pay_upon_invoice';
    public const VALUE_BLIK = 'blik';

    public const LABEL_BANCONTACT = 'Bancontact';
    public const LABEL_EPS = 'EPS';
    public const LABEL_IDEAL = 'iDEAL';
    public const LABEL_MYBANK = 'MyBank';
    public const LABEL_P24 = 'P24';
    public const LABEL_SEPA = 'SEPA/ELV Direct Debit';
    public const LABEL_PAY_UPON_INVOICE = 'Pay Upon Invoice';
    public const LABEL_BLIK = 'BLIK';

    private const COUNTRIES_BANCONTACT = ['BE'];
    private const COUNTRIES_EPS = ['AT'];
    private const COUNTRIES_IDEAL = ['NL'];
    private const COUNTRIES_MYBANK = ['IT'];
    private const COUNTRIES_P24 = ['PL'];
    private const COUNTRIES_SEPA = ['AT', 'DE'];
    private const COUNTRIES_PAY_UPON_INVOICE = ['DE'];
    private const COUNTRIES_BLIK = ['PL'];

    private const THRESHOLD_BANCONTACT = ['min' => 1];
    private const THRESHOLD_EPS = ['min' => 1];
    private const THRESHOLD_IDEAL = [];
    private const THRESHOLD_MYBANK = [];
    private const THRESHOLD_P24 = ['min' => 1, 'max' => 55000];
    private const THRESHOLD_SEPA = [];
    private const THRESHOLD_PAY_UPON_INVOICE = ['min' => 5, 'max' => 2500];
    private const THRESHOLD_BLIK = ['min' => 1, 'max' => 10000];

    private const CURRENCIES_BANCONTACT = ['EUR'];
    private const CURRENCIES_EPS = ['EUR'];
    private const CURRENCIES_IDEAL = [];
    private const CURRENCIES_MYBANK = [];
    private const CURRENCIES_P24 = ['PLN'];
    private const CURRENCIES_SEPA = ['EUR'];
    private const CURRENCIES_PAY_UPON_INVOICE = ['EUR'];
    private const CURRENCIES_BLIK = ['PLN'];

    private const CHECK_SHIPPING_BANCONTACT = false;
    private const CHECK_SHIPPING_EPS = false;
    private const CHECK_SHIPPING_IDEAL = false;
    private const CHECK_SHIPPING_MYBANK = false;
    private const CHECK_SHIPPING_P24 = false;
    private const CHECK_SHIPPING_SEPA = false;
    private const CHECK_SHIPPING_PAY_UPON_INVOICE = true;
    private const CHECK_SHIPPING_BLIK = false;

    /**
     * @var array
     */
    private array $removedLPMs = [
        'sofort',
        'giropay',
        'sepa'
    ];

    /**
     * @var StoreConfigResolver
     */
    private StoreConfigResolver $storeConfigResolver;

    /**
     * @var string
     */
    private string $clientToken = '';

    /**
     * @var BraintreeAdapter
     */
    private BraintreeAdapter $adapter;

    /**
     * @var BraintreeConfig
     */
    private BraintreeConfig $braintreeConfig;

    /**
     * @var array
     */
    private array $allowedMethods;

    /**
     * @var Repository
     */
    private Repository $assetRepo;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @param BraintreeAdapter $adapter
     * @param BraintreeConfig $braintreeConfig
     * @param StoreConfigResolver $storeConfigResolver
     * @param Repository $assetRepo
     * @param UrlInterface $urlBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        BraintreeAdapter $adapter,
        BraintreeConfig $braintreeConfig,
        StoreConfigResolver $storeConfigResolver,
        Repository $assetRepo,
        UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig,
        ?string $methodCode = null,
        string $pathPattern = \Magento\Payment\Gateway\Config\Config::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->adapter = $adapter;
        $this->braintreeConfig = $braintreeConfig;
        $this->storeConfigResolver = $storeConfigResolver;
        $this->assetRepo = $assetRepo;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Is method active
     *
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function isActive(): bool
    {
        return (bool) $this->getValue(
            self::KEY_ACTIVE,
            $this->storeConfigResolver->getStoreId()
        );
    }

    /**
     * Get allowed methods
     *
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getAllowedMethods(): array
    {
        $allowedMethodsValue = $this->getValue(
            self::KEY_ALLOWED_METHODS,
            $this->storeConfigResolver->getStoreId()
        );
        if ($allowedMethodsValue === null) {
            return [];
        }
        $allowedMethods = explode(
            ',',
            $allowedMethodsValue
        );

        foreach ($allowedMethods as $allowedMethod) {
            if (!in_array($allowedMethod, $this->removedLPMs)) {
                $this->allowedMethods[] = [
                    'method' => $allowedMethod,
                    'label' => constant('self::LABEL_' . strtoupper($allowedMethod)),
                    'countries' => constant('self::COUNTRIES_' . strtoupper($allowedMethod)),
                    'threshold' => constant('self::THRESHOLD_' . strtoupper($allowedMethod)),
                    'currencies' => constant('self::CURRENCIES_' . strtoupper($allowedMethod)),
                    'checkShipping' => constant('self::CHECK_SHIPPING_' . strtoupper($allowedMethod))
                ];
            }
        }

        return $this->allowedMethods;
    }

    /**
     * Get client token
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): string
    {
        if (empty($this->clientToken) && $this->isActive()) {
            $params = [];

            $merchantAccountId = $this->braintreeConfig->getMerchantAccountId();
            if (!empty($merchantAccountId)) {
                $params[PaymentDataBuilder::MERCHANT_ACCOUNT_ID] = $merchantAccountId;
            }

            $this->clientToken = $this->adapter->generate($params);
        }

        return $this->clientToken;
    }

    /**
     * Get merchant account id
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getMerchantAccountId(): ?string
    {
        return $this->braintreeConfig->getMerchantAccountId();
    }

    /**
     * Get payment icons
     *
     * @return array
     */
    public function getPaymentIcons(): array
    {
        return [
            self::VALUE_BANCONTACT => $this->assetRepo
                ->getUrl('PayPal_Braintree::images/' . self::VALUE_BANCONTACT . '.svg'),
            self::VALUE_EPS => $this->assetRepo
                ->getUrl('PayPal_Braintree::images/' . self::VALUE_EPS . '.svg'),
            self::VALUE_IDEAL => $this->assetRepo
                ->getUrl('PayPal_Braintree::images/' . self::VALUE_IDEAL . '.svg'),
            self::VALUE_MYBANK => $this->assetRepo
                ->getUrl('PayPal_Braintree::images/' . self::VALUE_MYBANK . '.svg'),
            self::VALUE_P24 => $this->assetRepo
                ->getUrl('PayPal_Braintree::images/' . self::VALUE_P24 . '.svg'),
            self::VALUE_SEPA => $this->assetRepo
                ->getUrl('PayPal_Braintree::images/' . self::VALUE_SEPA . '.svg'),
            self::VALUE_PAY_UPON_INVOICE => $this->assetRepo
                ->getUrl('PayPal_Braintree::images/' . self::VALUE_PAY_UPON_INVOICE . '.png'),
            self::VALUE_BLIK => $this->assetRepo
                ->getUrl('PayPal_Braintree::images/' . self::VALUE_BLIK . '.svg')
        ];
    }

    /**
     * Get title
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getTitle(): string
    {
        return $this->getValue(
            self::KEY_TITLE,
            $this->storeConfigResolver->getStoreId()
        );
    }

    /**
     * Get fallback url
     *
     * @return string
     */
    public function getFallbackUrl(): string
    {
        return $this->urlBuilder->getDirectUrl(self::LPM_FALLBACK_ACTION_URL);
    }

    /**
     * Get fallback button text
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getFallbackButtonText(): string
    {
        return $this->getValue(
            self::KEY_FALLBACK_BUTTON_TEXT,
            $this->storeConfigResolver->getStoreId()
        );
    }

    /**
     * Get redirect url on fail
     *
     * @return mixed|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getRedirectUrlOnFail(): mixed
    {
        return $this->getValue(
            self::KEY_REDIRECT_ON_FAIL,
            $this->storeConfigResolver->getStoreId()
        );
    }
}
