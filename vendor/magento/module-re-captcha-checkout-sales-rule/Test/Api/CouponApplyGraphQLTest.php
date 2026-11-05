<?php
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaCheckoutSalesRule\Test\Api;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\Quote\Test\Fixture\QuoteIdMask;
use Magento\SalesRule\Test\Fixture\Rule as SalesRuleFixture;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test graphql for couponApply
 */
class CouponApplyGraphQLTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = Bootstrap::getObjectManager()->get(DataFixtureStorageManager::class)->getStorage();
        $this->customerTokenService = Bootstrap::getObjectManager()->get(CustomerTokenServiceInterface::class);
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], as: 'cart'),
        DataFixture(
            AddProductToCart::class,
            ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 5],
            'cart_item'
        ),
        DataFixture(SetShippingAddress::class, ['cart_id' => '$cart.id$']),
        DataFixture(QuoteIdMask::class, ['cart_id' => '$cart.id$'], 'cart_mask'),
        DataFixture(
            SalesRuleFixture::class,
            [
                'coupon_code' => 'coupon%uniqid%',
                'discount_amount' => 5.00,
                'coupon_type' => 2,
                'simple_action' => 'by_fixed'
            ],
            'sales_rule'
        ),
        ConfigFixture('customer/captcha/enable', '0'),
        ConfigFixture('recaptcha_frontend/type_invisible/public_key', 'test_public_key'),
        ConfigFixture('recaptcha_frontend/type_invisible/private_key', 'test_private_key'),
        ConfigFixture('recaptcha_frontend/type_for/coupon_code', 'invisible')
    ]
    public function testCreateCouponApply(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('ReCaptcha validation failed, please try again');

        $this->graphQlMutation(
            $this->getApplyCouponToCartMutation(
                $this->fixtures->get('cart_mask')->getMaskedId(),
                $this->fixtures->get('sales_rule')->getCouponCode()
            ),
            [],
            '',
            $this->getCustomerAuthHeaders($this->fixtures->get('customer')->getEmail())
        );
    }

    /**
     * Prepare mutation for applying coupon to cart
     *
     * @param string $cartId
     * @return string
     */
    private function getApplyCouponToCartMutation(string $cartId, string $couponCode): string
    {
        return <<<MUTATION
            mutation {
              applyCouponToCart(
                input: {
                  cart_id:"{$cartId}",
                  coupon_code: "{$couponCode}"
                }
              ) {
                 cart{
                    applied_coupons {
                     code
                    }
                    prices {
                       grand_total{
                         value
                         currency
                       }
                    }
                 }
              }
            }
        MUTATION;
    }

    /**
     * Get customer authentication headers
     *
     * @param string $email
     * @return array
     * @throws AuthenticationException
     * @throws EmailNotConfirmedException
     */
    private function getCustomerAuthHeaders(string $email): array
    {
        $token = $this->customerTokenService->createCustomerAccessToken($email, 'password');

        return ['Authorization' => 'Bearer ' . $token];
    }
}
