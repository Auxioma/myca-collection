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

namespace Magento\PaymentServicesPaypal\Test\Unit\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\PaymentServicesPaypal\Block\Adminhtml\System\Config\ScopeOnboarding;

class TestableScopeOnboarding extends ScopeOnboarding
{
    /**
     * Expose element HTML rendering for tests.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function getElementHtml(AbstractElement $element): string
    {
        return parent::_getElementHtml($element);
    }

    /**
     * Build deterministic test URLs without relying on the Magento backend URL builder.
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl($route = '', $params = []): string
    {
        $queryParams = isset($params['_query']) && is_array($params['_query']) ? $params['_query'] : [];
        $query = $queryParams !== [] ? '?' . http_build_query($queryParams) : '';

        return 'https://admin.example/' . ltrim((string) $route, '/') . $query;
    }

    /**
     * Return deterministic template output for tests.
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        return 'scope-onboarding-html';
    }
}
