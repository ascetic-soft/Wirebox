<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

class CircularB
{
    public function __construct(
        public readonly CircularA $a,
    ) {
    }
}
