<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Test\Integration\Controller\Adminhtml\U2f;

use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\TwoFactorAuth\TestFramework\TestCase\AbstractConfigureBackendController;

/**
 * Test for the configure U2F 2FA form page.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class ConfigureTest extends AbstractConfigureBackendController
{
    /**
     * @var string
     */
    protected $uri = 'backend/tfa/u2f/configure';

    /**
     * @var string
     */
    protected $httpMethod = Request::METHOD_GET;

    /**
     * @magentoConfigFixture default/twofactorauth/general/force_providers u2fkey
     * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
     */
    public function testTokenAccess(): void
    {
        parent::testTokenAccess();
        $this->assertRedirect($this->stringContains('requestconfig'));
    }

    /**
     * @magentoConfigFixture default/twofactorauth/general/force_providers u2fkey
     * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
     */
    public function testAclHasAccess()
    {
        $this->expectedNoAccessResponseCode = 200;
        parent::testAclHasAccess();
    }

    /**
     * @magentoConfigFixture default/twofactorauth/general/force_providers u2fkey
     * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
     */
    public function testAclNoAccess()
    {
        parent::testAclNoAccess();
    }
}
