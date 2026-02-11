<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Fixtures;

class ServiceWithSetter
{
    public ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
