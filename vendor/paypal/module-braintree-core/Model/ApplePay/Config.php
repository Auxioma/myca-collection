<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\ApplePay;

/**
 * @deprecated as making code more consistent
 * @see \PayPal\Braintree\Gateway\Config\ApplePay\Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    /**
     * Get merchant name to display
     *
     * @return string
     */
    public function getMerchantName(): string
    {
        return $this->getValue('merchant_name');
    }
}
