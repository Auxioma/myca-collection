<?php
/**
 * ADOBE CONFIDENTIAL
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
 */

declare(strict_types=1);

namespace PayPal\Braintree\Block\Widget;

use Magento\Customer\Block\Widget\Dob;

/**
 * Extend default DOB widget to ensure it is always required.
 */
class LpmDob extends Dob
{
    /**
     * Check if dob attribute marked as required
     *
     * @return bool
     */
    public function isRequired()
    {
        return true;
    }

    /**
     * Create correct date field
     *
     * Override default value to set minimum of 18 years old.
     *
     * @return string
     */
    public function getFieldHtml()
    {
        $this->dateElement->setData($this->getDobConfig());
        return $this->dateElement->getHtml();
    }

    /**
     * Get the LPM DOB field configuration.
     *
     * @return array
     */
    public function getDobConfig()
    {
        return [
            'extraParams' => $this->getHtmlExtraParams(),
            'name' => $this->getHtmlId(),
            'id' => $this->getHtmlId(),
            'class' => $this->getHtmlClass(),
            'value' => $this->getValue(),
            'dateFormat' => $this->getDateFormat(),
            'buttonImage' => $this->getViewFileUrl('Magento_Theme::calendar.png'),
            'buttonText' => __('Select Date'),
            'showOn' => 'both',
            'yearsRange' => '-120y:c+nn',
            'maxDate' => '-18y',
            'changeMonth' => 'true',
            'changeYear' => 'true',
            'show_on' => 'both',
            'firstDay' => $this->getFirstDay()
        ];
    }
}
