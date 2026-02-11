<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

interface LoggerInterface
{
    public function log(string $message): void;
}
