<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Param;

readonly class ServiceWithOptionalParam
{
    public function __construct(
        #[Param('OPTIONAL')]
        public string $val = 'fallback',
    ) {
    }
}
