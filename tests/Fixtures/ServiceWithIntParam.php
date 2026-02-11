<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Param;

readonly class ServiceWithIntParam
{
    public function __construct(
        #[Param('PORT')]
        public int   $port,
        #[Param('RATE')]
        public float $rate,
        #[Param('DEBUG')]
        public bool  $debug,
    ) {
    }
}
