<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Param;

readonly class ServiceWithMissingParam
{
    public function __construct(
        #[Param('MISSING_PARAM')]
        public string $value,
    ) {
    }
}
