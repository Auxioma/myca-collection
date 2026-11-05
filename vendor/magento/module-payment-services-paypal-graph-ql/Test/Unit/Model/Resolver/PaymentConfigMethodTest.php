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

namespace Magento\PaymentServicesPaypalGraphQl\Test\Unit\Model\Resolver;

use Magento\Framework\DataObject;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\PaymentServicesPaypalGraphQl\Model\Resolver\PaymentConfigMethod;
use PHPUnit\Framework\TestCase;

class PaymentConfigMethodTest extends TestCase
{
    /**
     * @return void
     */
    public function testResolveReturnsNullForMissingField(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getName')->willReturn('logo');

        $resolver = new PaymentConfigMethod();

        $this->assertNull(
            $resolver->resolve(
                $field,
                null,
                $this->createMock(ResolveInfo::class),
                []
            )
        );
    }

    /**
     * @return void
     */
    public function testResolveReturnsDataFromConfiguredField(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getName')->willReturn('logo');
        $logo = new DataObject(['type' => 'inline']);

        $resolver = new PaymentConfigMethod();

        $this->assertSame(
            ['type' => 'inline'],
            $resolver->resolve(
                $field,
                null,
                $this->createMock(ResolveInfo::class),
                ['logo' => $logo]
            )
        );
    }
}
