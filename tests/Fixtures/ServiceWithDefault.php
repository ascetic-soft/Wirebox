<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

class ServiceWithDefault
{
    public function __construct(
        public readonly string $name = 'default',
        public readonly ?LoggerInterface $logger = null,
    ) {
    }
}
