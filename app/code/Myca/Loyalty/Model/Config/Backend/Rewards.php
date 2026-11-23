<?php

declare(strict_types=1);

namespace Myca\Loyalty\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Myca\Loyalty\Model\PointsCalculator;

/**
 * Vérifie le format des paliers « points=euros » avant enregistrement.
 */
class Rewards extends Value
{
    public function beforeSave(): self
    {
        $value = trim((string) $this->getValue());
        if ($value !== '' && PointsCalculator::parseRewards($value) === []) {
            throw new LocalizedException(__('Récompenses : utilisez le format « 100=6, 150=10 » (points=euros, valeurs positives).'));
        }
        $this->setValue($value);

        return parent::beforeSave();
    }
}
