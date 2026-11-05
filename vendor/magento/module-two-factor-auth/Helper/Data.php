<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TwoFactorAuth\Model\Config\UserNotifier as UserNotifierConfig;
use Magento\TwoFactorAuth\Model\Exception\NotificationException;
use Magento\User\Model\User;
use Magento\TwoFactorAuth\Api\UserConfigTokenManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Helper data
 */
class Data extends AbstractHelper
{
    /**
     * string
     */
    private const VERIFY_EMAIL_TEMPLATE = "user_identity_verify";

    /**
     * @param Context $context
     * @param UserConfigTokenManagerInterface $tokenManager
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param UserNotifierConfig $userNotifierConfig
     */
    public function __construct(
        Context $context,
        private readonly UserConfigTokenManagerInterface $tokenManager,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly UserNotifierConfig $userNotifierConfig
    ) {
        parent::__construct($context);
        $this->scopeConfig = $context->getScopeConfig();
    }

    /**
     * Send verification link to user
     *
     * @param User $user
     * @return void
     */
    public function sendIdentityVerificationLink(User $user)
    {
        $token = $this->tokenManager->issueFor((int) $user->getId());
        $this->sendConfigRequired(
            $user,
            $token,
            self::VERIFY_EMAIL_TEMPLATE,
            $this->userNotifierConfig->getIdentityVerificationUrl($token)
        );
    }

    /**
     * Send configuration related message to the admin user.
     *
     * @param User $user
     * @param string $token
     * @param string $emailTemplateId
     * @param string $url
     * @return void
     */
    private function sendConfigRequired(
        User $user,
        string $token,
        string $emailTemplateId,
        string $url
    ): void {
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($emailTemplateId)
                ->setTemplateOptions([
                    'area' => 'adminhtml',
                    'store' => 0
                ])
                ->setTemplateVars(
                    [
                        'username' => $user->getFirstName() . ' ' . $user->getLastName(),
                        'token' => $token,
                        'store_name' => $this->storeManager->getStore()->getFrontendName(),
                        'url' => $url
                    ]
                )
                ->setFromByScope(
                    $this->scopeConfig->getValue('admin/emails/forgot_email_identity')
                )
                ->addTo($user->getEmail(), $user->getFirstName() . ' ' . $user->getLastName())
                ->getTransport();
            $transport->sendMessage();
        } catch (\Throwable $exception) {
            $this->logger->critical($exception);
            throw new NotificationException('Failed to send 2FA E-mail to a user', 0, $exception);
        }
    }
}
