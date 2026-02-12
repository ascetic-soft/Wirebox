<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Lazy;
use AsceticSoft\Wirebox\Attribute\Transient;

#[Lazy]
#[Transient]
readonly class LazyTransientService
{
    public string $id;

    public function __construct()
    {
        $this->id = \uniqid('lazy_transient_', true);
    }
}
