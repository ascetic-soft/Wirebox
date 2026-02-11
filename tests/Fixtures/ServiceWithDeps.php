<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

class ServiceWithDeps
{
    public function __construct(
        public readonly SimpleService $simple,
    ) {
    }
}
