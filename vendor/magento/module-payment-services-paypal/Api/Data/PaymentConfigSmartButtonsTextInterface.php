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

namespace Magento\PaymentServicesPaypal\Api\Data;

interface PaymentConfigSmartButtonsTextInterface
{
    public const ALIGN = 'align';
    public const COLOR = 'color';
    public const SIZE = 'size';

    /**
     * Get align
     *
     * @return string|null
     */
    public function getAlign(): ?string;

    /**
     * Set align
     *
     * @param string $align
     * @return $this
     */
    public function setAlign(string $align): self;

    /**
     * Get color
     *
     * @return string|null
     */
    public function getColor(): ?string;

    /**
     * Set color
     *
     * @param string $color
     * @return $this
     */
    public function setColor(string $color): self;

    /**
     * Get size
     *
     * @return int|null
     */
    public function getSize(): ?int;

    /**
     * Set size
     *
     * @param int $size
     * @return $this
     */
    public function setSize(int $size): self;
}
