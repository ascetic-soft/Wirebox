<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

readonly class ServiceWithNullable
{
    public function __construct(
        public ?LoggerInterface $logger,
    ) {
    }
}
