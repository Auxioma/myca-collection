<?php
/*************************************************************************
 * ADOBE CONFIDENTIAL
 * ___________________
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
 **************************************************************************/
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Model\Api\Data;

use Magento\Framework\DataObject;
use Magento\PaymentServicesPaypal\Api\Data\PaymentConfigSmartButtonsLogoWithPositionInterface;

/**
 * Class Config Data
 */
class PaymentConfigSmartButtonsLogo extends DataObject implements PaymentConfigSmartButtonsLogoWithPositionInterface
{
    /**
     * @inheritdoc
     */
    public function getType()
    {
        return $this->getData(self::TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setType($type)
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritdoc
     */
    public function getPosition(): ?string
    {
        return $this->getData(self::POSITION);
    }

    /**
     * @inheritdoc
     */
    public function setPosition(string $position): PaymentConfigSmartButtonsLogoWithPositionInterface
    {
        return $this->setData(self::POSITION, $position);
    }
}
