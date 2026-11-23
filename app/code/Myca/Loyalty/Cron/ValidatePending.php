<?php

declare(strict_types=1);

namespace Myca\Loyalty\Cron;

use Myca\Loyalty\Model\LoyaltyService;

/**
 * Rend utilisables les points des commandes payées, livrées, dont le délai de retour est écoulé.
 */
class ValidatePending
{
    public function __construct(private readonly LoyaltyService $loyaltyService)
    {
    }

    public function execute(): void
    {
        $this->loyaltyService->validateDue();
    }
}
