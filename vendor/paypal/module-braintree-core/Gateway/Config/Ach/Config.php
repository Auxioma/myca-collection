<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2023 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Config\Ach;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Io\File;
use Magento\Payment\Model\CcConfig;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    /**
     * @var array
     */
    private array $icon = [];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CcConfig $ccConfig
     * @param File $fileIo
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CcConfig $ccConfig,
        private readonly File $fileIo,
        ?string $methodCode = null,
        string $pathPattern = self::DEFAULT_PATH_PATTERN,
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

    /**
     * Get ACH icon
     *
     * @return array
     */
    public function getAchIcon(): array
    {
        if (empty($this->icon)) {
            $asset = $this->ccConfig->createAsset('PayPal_Braintree::images/bank-account-us.png');
            $imageData = $this->fileIo->read($asset->getSourceFile());
            $imageSize = getimagesizefromstring($imageData);
            if ($imageSize !== false) {
                list($width, $height) = $imageSize;
                $this->icon = [
                    'url' => $asset->getUrl(),
                    'width' => $width,
                    'height' => $height
                ];
            }
        }

        return $this->icon;
    }
}
