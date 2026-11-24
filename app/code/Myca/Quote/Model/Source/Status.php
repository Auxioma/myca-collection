<?php

declare(strict_types=1);

namespace Myca\Quote\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Myca\Quote\Model\QuoteStatus;

class Status implements OptionSourceInterface
{
    public function __construct(private readonly QuoteStatus $quoteStatus)
    {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->quoteStatus->getLabels() as $value => $status) {
            $options[] = ['value' => $value, 'label' => $status['label']];
        }

        return $options;
    }
}
