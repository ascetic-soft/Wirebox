<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Lazy;

#[Lazy]
readonly class LazyServiceWithDeps
{
    public function __construct(
        public SimpleService $simple,
    ) {
    }
}
