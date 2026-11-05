<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaResendConfirmationEmail\Observer;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\RequestHandlerInterface;

class ResendConfirmationEmailObserver implements ObserverInterface
{
    private const KEY = 'resend_confirmation_email';

    /**
     * @param RedirectInterface $redirect
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     * @param RequestHandlerInterface $requestHandler
     */
    public function __construct(
        private readonly RedirectInterface $redirect,
        private readonly IsCaptchaEnabledInterface $isCaptchaEnabled,
        private readonly RequestHandlerInterface $requestHandler
    ) {
    }

    /**
     * Check captcha result if captcha has been enabled for this endpoint
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        if ($this->isCaptchaEnabled->isCaptchaEnabledFor(self::KEY)) {
            /** @var Action $controller */
            $controller = $observer->getControllerAction();
            $request = $controller->getRequest();
            $response = $controller->getResponse();
            $redirectOnFailureUrl = $this->redirect->getRefererUrl();

            $this->requestHandler->execute(self::KEY, $request, $response, $redirectOnFailureUrl);
        }
    }
}
