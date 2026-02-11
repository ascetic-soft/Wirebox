<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Inject;

class ServiceWithInject
{
    public function __construct(
        #[Inject(FileLogger::class)]
        public readonly LoggerInterface $logger,
    ) {
    }
}
