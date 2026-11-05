<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaResendConfirmationEmail\Test\Api\GraphQl\ResendConfirmationEmail;

use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * GraphQl test for resend confirmation email functionality with ReCaptcha enabled.
 */
class ResendConfirmationEmailTest extends GraphQlAbstract
{

    #[
        ConfigFixture('recaptcha_frontend/type_invisible/public_key', 'test_public_key'),
        ConfigFixture('recaptcha_frontend/type_invisible/private_key', 'test_private_key'),
        ConfigFixture('recaptcha_frontend/type_for/resend_confirmation_email', 'invisible')
    ]
    public function testResendConfirmationEmailReCaptchaValidationFailed(): void
    {
        $query = $this->getQuery("test@example.com");

        $this->expectExceptionMessage('ReCaptcha validation failed, please try again');
        $this->graphQlMutation($query);
    }

    /**
     * @param string $email
     * @return string
     */
    private function getQuery(string $email): string
    {
        return <<<QUERY
mutation {
    resendConfirmationEmail(
        email: "{$email}"
    )
}
QUERY;
    }
}
