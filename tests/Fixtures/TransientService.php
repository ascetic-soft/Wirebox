<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Transient;

#[Transient]
class TransientService
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid('', true);
    }
}
