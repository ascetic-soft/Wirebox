<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures\Scan;

class WithAttributedAnonymous
{
    public function create(): object
    {
        return new #[\AllowDynamicProperties] class {
        };
    }
}
