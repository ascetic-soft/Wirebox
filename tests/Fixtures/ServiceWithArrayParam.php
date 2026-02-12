<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Param;

readonly class ServiceWithArrayParam
{
    public function __construct(
        #[Param('CONFIG')]
        public array $config,
    ) {
    }
}
