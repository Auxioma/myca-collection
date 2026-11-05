<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\ReCaptchaCustomer\Test\Api\GraphQl\Customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * GraphQl test for update customer functionality with ReCaptcha enabled.
 */
class UpdateCustomerTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var CustomerInterface
     */
    private $customer;

    protected function setUp(): void
    {
        $this->customerTokenService = Bootstrap::getObjectManager()->get(CustomerTokenServiceInterface::class);
        $this->customer = DataFixtureStorageManager::getStorage()->get('customer');
    }

    #[
        DataFixture(Customer::class, as: 'customer'),
        ConfigFixture('recaptcha_frontend/type_invisible/public_key', 'test_public_key'),
        ConfigFixture('recaptcha_frontend/type_invisible/private_key', 'test_private_key'),
        ConfigFixture('recaptcha_frontend/type_for/customer_edit', 'invisible')
    ]
    public function testUpdateCustomerV2ReCaptchaValidationFailed(): void
    {
        $this->expectExceptionMessage('ReCaptcha validation failed, please try again');
        $this->graphQlMutation(
            $this->getUpdateCustomerV2Mutation(),
            [],
            '',
            $this->getCustomerAuthHeaders($this->customer->getEmail())
        );
    }

    #[
        DataFixture(Customer::class, as: 'customer'),
        ConfigFixture('recaptcha_frontend/type_invisible/public_key', 'test_public_key'),
        ConfigFixture('recaptcha_frontend/type_invisible/private_key', 'test_private_key'),
        ConfigFixture('recaptcha_frontend/type_for/customer_edit', 'invisible')
    ]
    public function testUpdateCustomerReCaptchaValidationFailed(): void
    {
        $this->expectExceptionMessage('ReCaptcha validation failed, please try again');
        $this->graphQlMutation(
            $this->getUpdateCustomerMutation(),
            [],
            '',
            $this->getCustomerAuthHeaders($this->customer->getEmail())
        );
    }

    /**
     * Get update customer graphql mutation
     *
     * @return string
     */
    private function getUpdateCustomerMutation(): string
    {
        return <<<MUTATION
            mutation {
                updateCustomer(
                    input: {
                        firstname: "Test User"
                    }
                ) {
                    customer {
                        firstname
                    }
                }
            }
        MUTATION;
    }

    /**
     * Get update customer V2 graphql mutation
     *
     * @return string
     */
    private function getUpdateCustomerV2Mutation(): string
    {
        return <<<MUTATION
            mutation {
                updateCustomerV2(
                    input: {
                        firstname: "Test User"
                    }
                ) {
                    customer {
                        firstname
                    }
                }
            }
        MUTATION;
    }

    /**
     * Get customer auth headers
     *
     * @param string $email
     * @return array
     * @throws AuthenticationException
     */
    private function getCustomerAuthHeaders(string $email): array
    {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($email, 'password');
        return ['Authorization' => 'Bearer ' . $customerToken];
    }
}
