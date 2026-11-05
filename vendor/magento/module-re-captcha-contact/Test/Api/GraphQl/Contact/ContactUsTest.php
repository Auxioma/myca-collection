<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\ReCaptchaContact\Test\Api\GraphQl\Contact;

use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * GraphQl test for contact us functionality with ReCaptcha enabled.
 */
class ContactUsTest extends GraphQlAbstract
{
    #[
        ConfigFixture('recaptcha_frontend/type_invisible/public_key', 'test_public_key'),
        ConfigFixture('recaptcha_frontend/type_invisible/private_key', 'test_private_key'),
        ConfigFixture('recaptcha_frontend/type_for/contact', 'invisible')
    ]
    public function testContactUsReCaptchaValidationFailed(): void
    {
        $this->expectExceptionMessage('ReCaptcha validation failed, please try again');
        $this->graphQlMutation($this->getContactUsMutation());
    }

    /**
     * Get contact us graphql mutation query
     *
     * @return string
     */
    private function getContactUsMutation(): string
    {
        return <<<MUTATION
            mutation {
                contactUs(input: {
                    comment:"Test Contact Us",
                    email:"test@adobe.com",
                    name:"John Doe",
                    telephone:"1111111111"
                })
                {
                    status
                }
            }
        MUTATION;
    }
}
