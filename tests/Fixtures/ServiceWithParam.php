<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Param;

class ServiceWithParam
{
    public function __construct(
        #[Param('DB_HOST')]
        public readonly string $dbHost,
        #[Param('APP_DEBUG')]
        public readonly bool $debug = false,
    ) {
    }
}
