<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Lazy;

#[Lazy]
class LazyService
{
    private static int $instanceCount = 0;

    public readonly string $id;

    public function __construct()
    {
        ++self::$instanceCount;
        $this->id = \uniqid('lazy_', true);
    }

    public static function getInstanceCount(): int
    {
        return self::$instanceCount;
    }

    public static function setInstanceCount(int $instanceCount): void
    {
        self::$instanceCount = $instanceCount;
    }

    public function hello(): string
    {
        return 'hello from lazy';
    }
}
