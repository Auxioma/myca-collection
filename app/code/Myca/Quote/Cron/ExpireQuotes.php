<?php

declare(strict_types=1);

namespace Myca\Quote\Cron;

use Myca\Quote\Model\QuoteManagement;

class ExpireQuotes
{
    public function __construct(private readonly QuoteManagement $quoteManagement)
    {
    }

    public function execute(): void
    {
        $this->quoteManagement->expireDue();
    }
}
