<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

use AsceticSoft\Wirebox\Attribute\Tag;

#[Tag('logger')]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
    }
}
