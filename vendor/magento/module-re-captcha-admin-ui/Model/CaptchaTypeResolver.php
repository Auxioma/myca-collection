<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaAdminUi\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\ReCaptchaUi\Model\CaptchaTypeResolverInterface;

/**
 * @inheritdoc
 */
class CaptchaTypeResolver implements CaptchaTypeResolverInterface
{
    private const XML_PATH_TYPE_FOR = 'recaptcha_backend/type_for/';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function getCaptchaTypeFor(string $key): ?string
    {
        $type = $this->scopeConfig->getValue(
            self::XML_PATH_TYPE_FOR . $key
        );
        return $type;
    }
}
