<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Represent country search results
 *
 * @api
 */
interface CountrySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get an array of objects
     *
     * @return CountryInterface[]
     */
    public function getItems(): array;

    /**
     * Set objects list
     *
     * @param CountryInterface[] $items
     * @return CountrySearchResultsInterface
     */
    public function setItems(array $items): CountrySearchResultsInterface;
}
