<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Param;

readonly class ServiceWithNonBuiltinParam
{
    /**
     * Union type parameter — castParameterValue gets a ReflectionUnionType
     * (not ReflectionNamedType), so it returns the value as-is.
     */
    public function __construct(
        #[Param('UNION_VAL')]
        public string|int $val,
    ) {
    }
}
