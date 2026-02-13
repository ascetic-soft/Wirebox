<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Eager;

#[Eager]
class EagerService
{
    public function hello(): string
    {
        return 'hello from eager';
    }
}
