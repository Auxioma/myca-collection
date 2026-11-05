<?php
/*************************************************************************
 * ADOBE CONFIDENTIAL
 * ___________________
 *
 * Copyright 2026 Adobe
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
 **************************************************************************/
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Model\Api\Data;

use Magento\Framework\DataObject;
use Magento\PaymentServicesPaypal\Api\Data\PaymentConfigSmartButtonsTextInterface;

/**
 * Class Config Data
 */
class PaymentConfigSmartButtonsText extends DataObject implements PaymentConfigSmartButtonsTextInterface
{
    /**
     * @inheritdoc
     */
    public function getAlign(): ?string
    {
        return $this->getData(self::ALIGN);
    }

    /**
     * @inheritdoc
     */
    public function setAlign(string $align): PaymentConfigSmartButtonsTextInterface
    {
        return $this->setData(self::ALIGN, $align);
    }

    /**
     * @inheritdoc
     */
    public function getColor(): ?string
    {
        return $this->getData(self::COLOR);
    }

    /**
     * @inheritdoc
     */
    public function setColor(string $color): PaymentConfigSmartButtonsTextInterface
    {
        return $this->setData(self::COLOR, $color);
    }

    /**
     * @inheritdoc
     */
    public function getSize(): ?int
    {
        $size = $this->getData(self::SIZE);

        return $size === null ? null : (int)$size;
    }

    /**
     * @inheritdoc
     */
    public function setSize(int $size): PaymentConfigSmartButtonsTextInterface
    {
        return $this->setData(self::SIZE, $size);
    }
}
