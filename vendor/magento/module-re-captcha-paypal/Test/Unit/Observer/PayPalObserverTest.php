<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaPaypal\Test\Unit\Observer;

use Magento\Framework\App\Action\AbstractAction;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Validation\ValidationResult;
use Magento\ReCaptchaPaypal\Model\ReCaptchaSession;
use Magento\ReCaptchaPaypal\Observer\PayPalObserver;
use Magento\ReCaptchaUi\Model\CaptchaResponseResolverInterface;
use Magento\ReCaptchaUi\Model\ErrorMessageConfigInterface;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\ValidatorInterface;
use Magento\ReCaptchaValidationApi\Model\ValidationErrorMessagesProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayPalObserverTest extends TestCase
{
    use MockCreationTrait;

    /**
     * @var ValidatorInterface|MockObject
     */
    private $captchaValidator;

    /**
     * @var IsCaptchaEnabledInterface|MockObject
     */
    private $isCaptchaEnabled;

    /**
     * @var ReCaptchaSession|MockObject
     */
    private $reCaptchaSession;

    /**
     * @var PayPalObserver
     */
    private $model;

    /**
     * @var Observer
     */
    private $observer;

    /**
     * @var ValidationResult|MockObject
     */
    private $validationResult;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $captchaResponseResolver = $this->createMock(CaptchaResponseResolverInterface::class);
        $validationConfigResolver = $this->createMock(ValidationConfigResolverInterface::class);
        $this->captchaValidator = $this->createMock(ValidatorInterface::class);
        $actionFlag = $this->createMock(ActionFlag::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $this->isCaptchaEnabled = $this->createMock(IsCaptchaEnabledInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $errorMessageConfig = $this->createMock(ErrorMessageConfigInterface::class);
        $validationErrorMessagesProvider = $this->createMock(ValidationErrorMessagesProvider::class);
        $this->reCaptchaSession = $this->createMock(ReCaptchaSession::class);
        $this->model = new PayPalObserver(
            $captchaResponseResolver,
            $validationConfigResolver,
            $this->captchaValidator,
            $actionFlag,
            $serializer,
            $this->isCaptchaEnabled,
            $logger,
            $errorMessageConfig,
            $validationErrorMessagesProvider,
            $this->reCaptchaSession
        );
        $controller = $this->createPartialMockWithReflection(
            AbstractAction::class,
            ['getRequest', 'getResponse', 'dispatch', 'execute']
        );
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createPartialMockWithReflection(
            ResponseInterface::class,
            ['representJson', 'sendResponse']
        );
        $controller->method('getRequest')->willReturn($request);
        $controller->method('getResponse')->willReturn($response);
        $this->observer = new Observer(['controller_action' => $controller]);
        $this->validationResult = $this->createMock(ValidationResult::class);
    }

    /**
     * @param array $mocks
     */
    #[DataProvider('executeDataProvider')]
    public function testExecute(array $mocks): void
    {
        $this->configureMock($mocks);
        $this->model->execute($this->observer);
    }

    public static function executeDataProvider(): array
    {
        return [
            [
                [
                    'isCaptchaEnabled' => [
                        [
                            'method' => 'isCaptchaEnabledFor',
                            'willReturnMap' => [
                                ['paypal_payflowpro', false],
                                ['place_order', false],
                            ]
                        ]
                    ],
                    'reCaptchaSession' => [
                        [
                            'method' => 'save',
                            'expects' => 'never',
                        ]
                    ]
                ]
            ],
            [
                [
                    'isCaptchaEnabled' => [
                        [
                            'method' => 'isCaptchaEnabledFor',
                            'willReturnMap' => [
                                ['paypal_payflowpro', true],
                                ['place_order', false],
                            ]
                        ]
                    ],
                    'reCaptchaSession' => [
                        [
                            'method' => 'save',
                            'expects' => 'never',
                        ]
                    ],
                    'captchaValidator' => [
                        [
                            'method' => 'isValid',
                            'expects' => 'once',
                            'willReturnProperty' => 'validationResult'
                        ]
                    ],
                    'validationResult' => [
                        [
                            'method' => 'isValid',
                            'expects' => 'once',
                            'willReturn' => true,
                        ]
                    ]
                ]
            ],
            [
                [
                    'isCaptchaEnabled' => [
                        [
                            'method' => 'isCaptchaEnabledFor',
                            'willReturnMap' => [
                                ['paypal_payflowpro', true],
                                ['place_order', true],
                            ]
                        ]
                    ],
                    'reCaptchaSession' => [
                        [
                            'method' => 'save',
                            'expects' => 'once',
                        ]
                    ],
                    'captchaValidator' => [
                        [
                            'method' => 'isValid',
                            'expects' => 'once',
                            'willReturnProperty' => 'validationResult'
                        ]
                    ],
                    'validationResult' => [
                        [
                            'method' => 'isValid',
                            'expects' => 'once',
                            'willReturn' => true,
                        ]
                    ]
                ]
            ]
        ];
    }

    private function configureMock(array $mocks): void
    {
        foreach ($mocks as $prop => $propMocks) {
            foreach ($propMocks as $mock) {
                $expectsValue = $mock['expects'] ?? 'any';
                $expects = $this->createInvocationMatcher($expectsValue);
                $builder = $this->$prop->expects($expects);
                unset($mock['expects']);
                foreach ($mock as $method => $args) {
                    if ($method === 'willReturnProperty') {
                        $method = 'willReturn';
                        $args = $this->$args;
                    }
                    $builder->$method(...[$args]);
                }
            }
        }
    }
}
