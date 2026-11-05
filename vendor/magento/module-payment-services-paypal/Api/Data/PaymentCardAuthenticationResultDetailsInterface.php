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

namespace Magento\PaymentServicesPaypal\Api\Data;

interface PaymentCardAuthenticationResultDetailsInterface
{
    public const LIABILITY_SHIFT = 'liability_shift';

    /**
     * Get liability shift
     *
     * @return string|null
     */
    public function getLiabilityShift(): ?string;

    /**
     * Set liability shift
     *
     * @param string|null $liabilityShift
     * @return $this
     */
    public function setLiabilityShift(?string $liabilityShift): PaymentCardAuthenticationResultDetailsInterface;
}
