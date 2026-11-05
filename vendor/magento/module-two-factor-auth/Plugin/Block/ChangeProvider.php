<?php
/**
 * Copyright 2020 Adobe.
 * All rights reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Plugin\Block;

use Magento\TwoFactorAuth\Block\ChangeProvider as Subject;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;

/**
 * Change provider plugin
 */
class ChangeProvider
{
    /**
     * @param TfaProviderSessionInterface $tfaProviderSession
     */
    public function __construct(
        private readonly TfaProviderSessionInterface $tfaProviderSession
    ) {
    }

    /**
     * After plugin to update auth url
     *
     * @param Subject $subject
     * @param string $result
     * @return false|string
     */
    public function afterGetJsLayout(
        Subject $subject,
        $result
    ) {
        try {
            $result = json_decode($result, true);
            $providers = $result['components']['tfa-change-provider']['providers'] ?? [];

            if ($providers) {
                foreach ($providers as $key => $provider) {
                    if (!empty($provider['is_configured']) ||
                        $this->tfaProviderSession->isNewProviderConfigurationAllowed()) {
                        continue;
                    }
                    $providers[$key]['auth'] = $subject->getUrl(
                        'tfa/tfa/providerselection',
                        ['provider' => $provider['code']]
                    );
                }
            }
            $result['components']['tfa-change-provider']['providers'] = $providers;

            return json_encode($result);
        } catch (\Exception $e) {
            return json_encode($result);
        }
    }
}
