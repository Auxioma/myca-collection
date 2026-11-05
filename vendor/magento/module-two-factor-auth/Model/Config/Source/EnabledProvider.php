<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;

/**
 * List of enabled providers
 */
class EnabledProvider implements OptionSourceInterface
{
    /**
     * @var TfaInterface
     */
    private $tfa;

    /**
     * @param TfaInterface $tfa
     */
    public function __construct(
        TfaInterface $tfa
    ) {
        $this->tfa = $tfa;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $providers = $this->tfa->getAllProviders();
        $res = [];
        foreach ($providers as $provider) {
            if ($provider->isEnabled()) {
                $res[] = [
                    'value' => $provider->getCode(),
                    'label' => $provider->getName(),
                ];
            }
        }

        return $res;
    }
}
