<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures\Scan;

class WithAnonymousClass
{
    public function createAnonymous(): object
    {
        return new class {
            public function hello(): string
            {
                return 'anonymous';
            }
        };
    }
}
