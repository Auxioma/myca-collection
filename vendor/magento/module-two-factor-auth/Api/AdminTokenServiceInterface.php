<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Api;

use Magento\Integration\Api\AdminTokenServiceInterface as OriginalTokenServiceInterface;

/**
 * Obtain basic information about the user required to setup or use 2fa
 *
 * @api
 */
interface AdminTokenServiceInterface extends OriginalTokenServiceInterface
{

}
