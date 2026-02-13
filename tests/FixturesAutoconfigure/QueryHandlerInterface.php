<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesAutoconfigure;

use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

/**
 * Interface with #[AutoconfigureTag] — all implementing classes
 * automatically receive the 'query.handler' tag.
 */
#[AutoconfigureTag('query.handler')]
interface QueryHandlerInterface
{
    public function __invoke(object $query): mixed;
}
