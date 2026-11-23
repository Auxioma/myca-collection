<?php

declare(strict_types=1);

namespace Myca\Loyalty\Cron;

use Myca\Loyalty\Model\LoyaltyService;

class ExpireInactive
{
    public function __construct(private readonly LoyaltyService $loyaltyService)
    {
    }

    public function execute(): void
    {
        $this->loyaltyService->expireInactive();
    }
}
