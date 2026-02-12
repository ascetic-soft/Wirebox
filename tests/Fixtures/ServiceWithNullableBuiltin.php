<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

readonly class ServiceWithNullableBuiltin
{
    public function __construct(
        public ?string $name,
    ) {
    }
}
