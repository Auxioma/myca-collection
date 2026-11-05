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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\CustomCapture;

use Magento\PaymentServicesPaypal\Model\CustomCapture\CommentFormatter;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Validator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CommentFormatterTest extends TestCase
{
    /**
     * @var CommentFormatter
     */
    private CommentFormatter $formatter;

    /**
     * @var Validator|MockObject
     */
    private Validator|MockObject $validator;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(Validator::class);
        $this->formatter = new CommentFormatter($this->validator);
    }

    public function testFormatIncludesOnlyReadableAmountWhenReasonIsEmpty(): void
    {
        $this->validator->expects($this->once())->method('normalizeReason')->with('')->willReturn('');

        $comment = $this->formatter->format('$30.00');

        $this->assertSame('Additional Amount: $30.00', $comment);
        $this->assertStringNotContainsString('[payment_services_custom_capture]', $comment);
        $this->assertStringNotContainsString('{"amount"', $comment);
    }

    public function testFormatIncludesReadableAmountAndReason(): void
    {
        $this->validator->expects($this->once())
            ->method('normalizeReason')
            ->with('Increased shipping')
            ->willReturn('Increased shipping');

        $comment = $this->formatter->format('$30.00', 'Increased shipping');

        $this->assertSame(
            "Additional Amount: $30.00\nReason for Additional Amount: Increased shipping",
            $comment
        );
        $this->assertStringNotContainsString('[payment_services_custom_capture]', $comment);
        $this->assertStringNotContainsString('{"amount"', $comment);
    }

    public function testFormatRemovesHtmlFromReason(): void
    {
        $reason = '<em>Increased</em> <b>shipping</b>';
        $this->validator->expects($this->once())
            ->method('normalizeReason')
            ->with($reason)
            ->willReturn('Increased shipping');

        $comment = $this->formatter->format('$30.00', $reason);

        $this->assertSame(
            "Additional Amount: $30.00\nReason for Additional Amount: Increased shipping",
            $comment
        );
    }
}
