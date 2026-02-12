<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures\Scan;

class WithDoubleColon
{
    public function getRef(): string
    {
        return ConcreteClass::class;
    }
}
