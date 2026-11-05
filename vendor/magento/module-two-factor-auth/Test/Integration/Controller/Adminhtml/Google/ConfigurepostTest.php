<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Test\Integration\Controller\Adminhtml\Google;

use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\TwoFactorAuth\TestFramework\TestCase\AbstractConfigureBackendController;

/**
 * Test for the configure google 2FA form page processor.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class ConfigurepostTest extends AbstractConfigureBackendController
{
    /**
     * @var string
     */
    protected $uri = 'backend/tfa/google/configurepost';

    /**
     * @var string
     */
    protected $httpMethod = Request::METHOD_POST;

    /**
     * @inheritDoc
     * @magentoConfigFixture default/twofactorauth/general/force_providers google
     * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
     */
    public function testTokenAccess(): void
    {
        parent::testTokenAccess();
        $this->assertRedirect($this->stringContains('requestconfig'));
    }

    /**
     * @inheritDoc
     * @magentoConfigFixture default/twofactorauth/general/force_providers google
     * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
     */
    public function testAclHasAccess()
    {
        $this->expectedNoAccessResponseCode = 200;
        parent::testAclHasAccess();
    }

    /**
     * @inheritDoc
     * @magentoConfigFixture default/twofactorauth/general/force_providers google
     * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
     */
    public function testAclNoAccess()
    {
        parent::testAclNoAccess();
    }
}
