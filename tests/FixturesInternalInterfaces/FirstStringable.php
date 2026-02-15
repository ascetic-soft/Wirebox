<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesInternalInterfaces;

class FirstStringable implements \Stringable
{
    public function __toString(): string
    {
        return 'first';
    }
}
