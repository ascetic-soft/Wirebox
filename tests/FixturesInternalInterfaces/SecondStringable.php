<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesInternalInterfaces;

class SecondStringable implements \Stringable
{
    public function __toString(): string
    {
        return 'second';
    }
}
