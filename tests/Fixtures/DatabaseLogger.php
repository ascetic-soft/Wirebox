<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Tag;

#[Tag('logger')]
class DatabaseLogger implements LoggerInterface
{
    public function log(string $message): void
    {
    }
}
