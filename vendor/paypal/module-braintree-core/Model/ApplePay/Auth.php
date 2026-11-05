<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\ApplePay;

use Magento\Framework\App\ObjectManager;
use PayPal\Braintree\Api\AuthInterface;
use PayPal\Braintree\Api\Data\AuthDataInterface;
use PayPal\Braintree\Api\Data\AuthDataInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\Braintree\Gateway\Config\ApplePay\Config;
use PayPal\Braintree\Model\ApplePay\Ui\ConfigProvider;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Auth implements AuthInterface
{
    /**
     * Auth constructor
     *
     * @param AuthDataInterfaceFactory $authData
     * @param ConfigProvider $configProvider
     * @param UrlInterface $url
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param Config|null $applePayConfig
     */
    public function __construct(
        private readonly AuthDataInterfaceFactory $authData,
        private readonly ConfigProvider $configProvider,
        private readonly UrlInterface $url,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private ?Config $applePayConfig = null
    ) {
        $this->applePayConfig = $applePayConfig ?: ObjectManager::getInstance()->get(Config::class);
    }

    /**
     * @inheritdoc
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get(): AuthDataInterface
    {
        /** @var AuthDataInterface $data */
        $data = $this->authData->create();
        $data->setClientToken($this->getClientToken());
        $data->setDisplayName($this->getDisplayName());
        $data->setActionSuccess($this->getActionSuccess());
        $data->setIsLoggedIn($this->isLoggedIn());
        $data->setStoreCode($this->getStoreCode());

        return $data;
    }

    /**
     * Get client token
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    protected function getClientToken(): ?string
    {
        return $this->configProvider->getClientToken();
    }

    /**
     * Get display name
     *
     * @return string|null
     */
    protected function getDisplayName(): ?string
    {
        return $this->applePayConfig->getMerchantName();
    }

    /**
     * Get action success url
     *
     * @return string
     */
    protected function getActionSuccess(): string
    {
        return $this->url->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    /**
     * Check if logged in
     *
     * @return bool
     */
    protected function isLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * Get store code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getStoreCode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return $this->applePayConfig->getSortOrder();
    }
}
