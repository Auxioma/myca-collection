<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaCustomer\Model;

use Magento\CustomerGraphQl\Model\Resolver\ChangePassword;
use Magento\CustomerGraphQl\Model\Resolver\RequestPasswordResetEmail;
use Magento\CustomerGraphQl\Model\Resolver\ResetPassword;
use Magento\CustomerGraphQl\Model\Resolver\UpdateCustomer;
use Magento\Framework\Exception\InputException;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use Magento\ReCaptchaWebapiApi\Api\WebapiValidationConfigProviderInterface;

/**
 * Provide customer related endpoint configuration.
 */
class WebapiConfigProvider implements WebapiValidationConfigProviderInterface
{
    private const RESET_PASSWORD_CAPTCHA_ID = 'customer_forgot_password';

    private const CUSTOMER_EDIT_CAPTCHA_ID = 'customer_edit';

    private const LOGIN_CAPTCHA_ID = 'customer_login';

    private const CREATE_CAPTCHA_ID = 'customer_create';

    /**
     * @var IsCaptchaEnabledInterface
     */
    private $isEnabled;

    /**
     * @var ValidationConfigResolverInterface
     */
    private $configResolver;

    /**
     * @param IsCaptchaEnabledInterface $isEnabled
     * @param ValidationConfigResolverInterface $configResolver
     */
    public function __construct(IsCaptchaEnabledInterface $isEnabled, ValidationConfigResolverInterface $configResolver)
    {
        $this->isEnabled = $isEnabled;
        $this->configResolver = $configResolver;
    }

    /**
     * Validates ifChangedPasswordCaptchaEnabled using captcha_id
     *
     * @param string $serviceMethod
     * @param string $serviceClass
     * @return ValidationConfigInterface|null
     * @throws InputException
     */
    private function validateChangePasswordCaptcha($serviceMethod, $serviceClass): ?ValidationConfigInterface
    {
        if ($this->isResetPasswordCase($serviceMethod, $serviceClass)) {
            $captchaId = self::RESET_PASSWORD_CAPTCHA_ID;
        } elseif ($this->isChangePasswordCase($serviceMethod, $serviceClass)) {
            $captchaId = self::CUSTOMER_EDIT_CAPTCHA_ID;
        }

        if (isset($captchaId) && $this->isEnabled->isCaptchaEnabledFor($captchaId)) {
            return $this->configResolver->get($captchaId);
        }

        return null;
    }

    /**
     * Check if the request is related to reset password
     *
     * @param string $serviceMethod
     * @param string $serviceClass
     * @return bool
     */
    private function isResetPasswordCase(string $serviceMethod, string $serviceClass): bool
    {
        return in_array($serviceMethod, ['resetPassword', 'initiatePasswordReset'], true)
            || in_array($serviceClass, [ResetPassword::class, RequestPasswordResetEmail::class], true);
    }

    /**
     * Check if the request is related to change password
     *
     * @param string $serviceMethod
     * @param string $serviceClass
     * @return bool
     */
    private function isChangePasswordCase(string $serviceMethod, string $serviceClass): bool
    {
        return $serviceMethod === 'changePasswordById'
            || in_array($serviceClass, [ChangePassword::class, UpdateCustomer::class], true);
    }

    /**
     * Validates ifLoginCaptchaEnabled using captcha_id
     *
     * @return ValidationConfigInterface|null
     */
    private function validateLoginCaptcha(): ?ValidationConfigInterface
    {
        return  $this->isEnabled->isCaptchaEnabledFor(self::LOGIN_CAPTCHA_ID) ?
            $this->configResolver->get(self::LOGIN_CAPTCHA_ID) : null;
    }

    /**
     * Validates ifCreateCustomerCaptchaEnabled using captcha_id
     *
     * @return ValidationConfigInterface|null
     */
    private function validateCreateCustomerCaptcha(): ?ValidationConfigInterface
    {
        return  $this->isEnabled->isCaptchaEnabledFor(self::CREATE_CAPTCHA_ID) ?
            $this->configResolver->get(self::CREATE_CAPTCHA_ID) : null;
    }

    /**
     * @inheritDoc
     */
    public function getConfigFor(EndpointInterface $endpoint): ?ValidationConfigInterface
    {
        $serviceClass = $endpoint->getServiceClass();
        $serviceMethod = $endpoint->getServiceMethod();

        //phpcs:disable Magento2.PHP.LiteralNamespaces
        if (($serviceClass === 'Magento\Integration\Api\CustomerTokenServiceInterface'
                && $serviceMethod === 'createCustomerAccessToken')
            || $serviceClass === 'Magento\CustomerGraphQl\Model\Resolver\GenerateCustomerToken'
        ) {
            return $this->validateLoginCaptcha();
        } elseif (($serviceClass === 'Magento\Customer\Api\AccountManagementInterface'
                && $serviceMethod === 'createAccount')
            || $serviceClass === 'Magento\CustomerGraphQl\Model\Resolver\CreateCustomer'
        ) {
            return $this->validateCreateCustomerCaptcha();
        }
        //phpcs:enable Magento2.PHP.LiteralNamespaces

        return $this->validateChangePasswordCaptcha($serviceMethod, $serviceClass);
    }
}
