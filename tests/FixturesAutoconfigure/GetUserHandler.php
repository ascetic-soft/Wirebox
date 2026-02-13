<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesAutoconfigure;

class GetUserHandler implements QueryHandlerInterface
{
    public function __invoke(object $query): mixed
    {
        return null;
    }
}
