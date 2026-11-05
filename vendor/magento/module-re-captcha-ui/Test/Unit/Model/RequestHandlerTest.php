<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaUi\Test\Unit\Model;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\HttpInterface as HttpResponseInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\ReCaptchaUi\Model\CaptchaResponseResolverInterface;
use Magento\ReCaptchaUi\Model\ErrorMessageConfigInterface;
use Magento\ReCaptchaUi\Model\RequestHandler;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface as ApiValidationConfigInterface;
use Magento\Framework\Validation\ValidationResult;
use Magento\ReCaptchaValidationApi\Api\ValidatorInterface;
use Magento\ReCaptchaValidationApi\Model\ValidationErrorMessagesProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for RequestHandler class
 *
 * Tests the reCAPTCHA request handling functionality including validation,
 * error handling, and response processing for various scenarios.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RequestHandlerTest extends TestCase
{
    /**
     * @var CaptchaResponseResolverInterface|MockObject
     */
    private $captchaResponseResolverMock;

    /**
     * @var ValidationConfigResolverInterface|MockObject
     */
    private $validationConfigResolverMock;

    /**
     * @var ValidatorInterface|MockObject
     */
    private $captchaValidatorMock;

    /**
     * @var MessageManagerInterface|MockObject
     */
    private $messageManagerMock;

    /**
     * @var ActionFlag|MockObject
     */
    private $actionFlagMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var ErrorMessageConfigInterface|MockObject
     */
    private $errorMessageConfigMock;

    /**
     * @var ValidationErrorMessagesProvider|MockObject
     */
    private $validationErrorMessagesProviderMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var HttpResponseInterface|MockObject
     */
    private $responseMock;

    /**
     * @var ApiValidationConfigInterface|MockObject
     */
    private $validationConfigMock;

    /**
     * @var ValidationResult|MockObject
     */
    private $validationResultMock;

    /**
     * @var RequestHandler
     */
    private $requestHandler;

    protected function setUp(): void
    {
        $this->captchaResponseResolverMock = $this->createMock(CaptchaResponseResolverInterface::class);
        $this->validationConfigResolverMock = $this->createMock(ValidationConfigResolverInterface::class);
        $this->captchaValidatorMock = $this->createMock(ValidatorInterface::class);
        $this->messageManagerMock = $this->createMock(MessageManagerInterface::class);
        $this->actionFlagMock = $this->createMock(ActionFlag::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->errorMessageConfigMock = $this->createMock(ErrorMessageConfigInterface::class);
        $this->validationErrorMessagesProviderMock = $this->createMock(ValidationErrorMessagesProvider::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->responseMock = $this->createMock(HttpResponseInterface::class);
        $this->validationConfigMock = $this->createMock(ApiValidationConfigInterface::class);
        $this->validationResultMock = $this->createMock(ValidationResult::class);

        $this->requestHandler = new RequestHandler(
            $this->captchaResponseResolverMock,
            $this->validationConfigResolverMock,
            $this->captchaValidatorMock,
            $this->messageManagerMock,
            $this->actionFlagMock,
            $this->loggerMock,
            $this->errorMessageConfigMock,
            $this->validationErrorMessagesProviderMock
        );
    }

    /**
     * Test successful reCAPTCHA validation
     */
    public function testExecuteWithValidCaptchaResponse()
    {
        $key = 'customer_login';
        $redirectOnFailureUrl = '/customer/account/login';
        $reCaptchaResponse = 'valid-captcha-response';

        $this->validationConfigResolverMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($this->validationConfigMock);

        $this->captchaResponseResolverMock->expects($this->once())
            ->method('resolve')
            ->with($this->requestMock)
            ->willReturn($reCaptchaResponse);

        $this->validationResultMock->expects($this->once())
            ->method('isValid')
            ->willReturn(true);

        $this->captchaValidatorMock->expects($this->once())
            ->method('isValid')
            ->with($reCaptchaResponse, $this->validationConfigMock)
            ->willReturn($this->validationResultMock);

        // These should not be called for successful validation
        $this->messageManagerMock->expects($this->never())->method('addErrorMessage');
        $this->actionFlagMock->expects($this->never())->method('set');
        $this->responseMock->expects($this->never())->method('setRedirect');

        $this->requestHandler->execute($key, $this->requestMock, $this->responseMock, $redirectOnFailureUrl);
    }

    /**
     * Test reCAPTCHA validation failure
     */
    public function testExecuteWithInvalidCaptchaResponse()
    {
        $key = 'customer_login';
        $redirectOnFailureUrl = '/customer/account/login';
        $reCaptchaResponse = 'invalid-captcha-response';
        $errorMessages = ['invalid-input-response' => 'Invalid reCAPTCHA response'];
        $validationErrorText = 'reCAPTCHA validation failed';

        $this->validationConfigResolverMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($this->validationConfigMock);

        $this->captchaResponseResolverMock->expects($this->once())
            ->method('resolve')
            ->with($this->requestMock)
            ->willReturn($reCaptchaResponse);

        $this->validationResultMock->expects($this->once())
            ->method('isValid')
            ->willReturn(false);

        $this->validationResultMock->expects($this->once())
            ->method('getErrors')
            ->willReturn($errorMessages);

        $this->captchaValidatorMock->expects($this->once())
            ->method('isValid')
            ->with($reCaptchaResponse, $this->validationConfigMock)
            ->willReturn($this->validationResultMock);

        $this->errorMessageConfigMock->expects($this->once())
            ->method('getValidationFailureMessage')
            ->willReturn($validationErrorText);

        $this->validationErrorMessagesProviderMock->expects($this->once())
            ->method('getErrorMessage')
            ->with('invalid-input-response')
            ->willReturn('Invalid reCAPTCHA response');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($validationErrorText);

        $this->actionFlagMock->expects($this->once())
            ->method('set')
            ->with('', Action::FLAG_NO_DISPATCH, true);

        $this->responseMock->expects($this->once())
            ->method('setRedirect')
            ->with($redirectOnFailureUrl);

        $this->requestHandler->execute($key, $this->requestMock, $this->responseMock, $redirectOnFailureUrl);
    }

    /**
     * Test InputException handling - this covers the specific change made
     */
    public function testExecuteWithInputException()
    {
        $key = 'customer_login';
        $redirectOnFailureUrl = '/customer/account/login';
        $errorMessage = 'Missing reCAPTCHA response';
        $validationErrorText = 'reCAPTCHA validation failed';

        $inputException = new InputException(__($errorMessage));

        $this->validationConfigResolverMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($this->validationConfigMock);

        $this->captchaResponseResolverMock->expects($this->once())
            ->method('resolve')
            ->with($this->requestMock)
            ->willThrowException($inputException);

        // The validator should not be called when InputException is thrown
        $this->captchaValidatorMock->expects($this->never())->method('isValid');

        $this->errorMessageConfigMock->expects($this->once())
            ->method('getValidationFailureMessage')
            ->willReturn($validationErrorText);

        $this->validationErrorMessagesProviderMock->expects($this->once())
            ->method('getErrorMessage')
            ->with('missing-input-response')
            ->willReturn('Missing reCAPTCHA response');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($validationErrorText);

        $this->actionFlagMock->expects($this->once())
            ->method('set')
            ->with('', Action::FLAG_NO_DISPATCH, true);

        $this->responseMock->expects($this->once())
            ->method('setRedirect')
            ->with($redirectOnFailureUrl);

        $this->requestHandler->execute($key, $this->requestMock, $this->responseMock, $redirectOnFailureUrl);
    }

    /**
     * Test technical error handling
     */
    public function testExecuteWithTechnicalError()
    {
        $key = 'customer_login';
        $redirectOnFailureUrl = '/customer/account/login';
        $reCaptchaResponse = 'captcha-response';
        $errorMessages = ['unknown-error' => 'Unknown technical error'];
        $technicalErrorText = 'Technical error occurred';

        $this->validationConfigResolverMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($this->validationConfigMock);

        $this->captchaResponseResolverMock->expects($this->once())
            ->method('resolve')
            ->with($this->requestMock)
            ->willReturn($reCaptchaResponse);

        $this->validationResultMock->expects($this->once())
            ->method('isValid')
            ->willReturn(false);

        $this->validationResultMock->expects($this->once())
            ->method('getErrors')
            ->willReturn($errorMessages);

        $this->captchaValidatorMock->expects($this->once())
            ->method('isValid')
            ->with($reCaptchaResponse, $this->validationConfigMock)
            ->willReturn($this->validationResultMock);

        $this->errorMessageConfigMock->expects($this->once())
            ->method('getTechnicalFailureMessage')
            ->willReturn($technicalErrorText);

        $this->validationErrorMessagesProviderMock->expects($this->once())
            ->method('getErrorMessage')
            ->with('unknown-error')
            ->willReturn('unknown-error');

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->callback(function ($phrase) {
                // The logger receives a Magento\Framework\Phrase object
                return (string)$phrase === (string)__(
                    "reCAPTCHA '%1' form error: %2",
                    'customer_login',
                    'Unknown technical error'
                );
            }));

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($technicalErrorText);

        $this->actionFlagMock->expects($this->once())
            ->method('set')
            ->with('', Action::FLAG_NO_DISPATCH, true);

        $this->responseMock->expects($this->once())
            ->method('setRedirect')
            ->with($redirectOnFailureUrl);

        $this->requestHandler->execute($key, $this->requestMock, $this->responseMock, $redirectOnFailureUrl);
    }

    /**
     * Test empty error messages handling
     */
    public function testExecuteWithEmptyErrorMessages()
    {
        $key = 'customer_login';
        $redirectOnFailureUrl = '/customer/account/login';
        $reCaptchaResponse = 'captcha-response';
        $errorMessages = [];
        $technicalErrorText = 'Technical error occurred';

        $this->validationConfigResolverMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($this->validationConfigMock);

        $this->captchaResponseResolverMock->expects($this->once())
            ->method('resolve')
            ->with($this->requestMock)
            ->willReturn($reCaptchaResponse);

        $this->validationResultMock->expects($this->once())
            ->method('isValid')
            ->willReturn(false);

        $this->validationResultMock->expects($this->once())
            ->method('getErrors')
            ->willReturn($errorMessages);

        $this->captchaValidatorMock->expects($this->once())
            ->method('isValid')
            ->with($reCaptchaResponse, $this->validationConfigMock)
            ->willReturn($this->validationResultMock);

        $this->errorMessageConfigMock->expects($this->once())
            ->method('getTechnicalFailureMessage')
            ->willReturn($technicalErrorText);

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($technicalErrorText);

        $this->actionFlagMock->expects($this->once())
            ->method('set')
            ->with('', Action::FLAG_NO_DISPATCH, true);

        $this->responseMock->expects($this->once())
            ->method('setRedirect')
            ->with($redirectOnFailureUrl);

        $this->requestHandler->execute($key, $this->requestMock, $this->responseMock, $redirectOnFailureUrl);
    }
}
