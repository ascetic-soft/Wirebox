<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

readonly class ServiceWithUnion
{
    public function __construct(
        public LoggerInterface|SimpleService $dependency,
    ) {
    }
}
