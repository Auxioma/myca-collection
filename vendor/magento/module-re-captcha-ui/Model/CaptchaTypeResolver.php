<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaUi\Model;

/**
 * Composite type resolver for multiple areas.
 */
class CaptchaTypeResolver implements CaptchaTypeResolverInterface
{
    /**
     * @var CaptchaTypeResolverInterface[]
     */
    private $resolvers;

    /**
     * @param CaptchaTypeResolverInterface[] $resolvers
     */
    public function __construct(array $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    /**
     * @inheritDoc
     */
    public function getCaptchaTypeFor(string $key): ?string
    {
        foreach ($this->resolvers as $area => $resolver) {
            if ($type = $resolver->getCaptchaTypeFor($key)) {
                return $area .'_' .$type;
            }
        }

        return null;
    }
}
