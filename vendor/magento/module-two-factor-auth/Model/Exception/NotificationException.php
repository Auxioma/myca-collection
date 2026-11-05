<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Model\Exception;

use Magento\TwoFactorAuth\Api\Exception\NotificationExceptionInterface;

/**
 * @inheritDoc
 */
class NotificationException extends \RuntimeException implements NotificationExceptionInterface
{

}
