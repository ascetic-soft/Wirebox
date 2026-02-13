<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesAutoconfigure;

use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

/**
 * Interface with #[AutoconfigureTag].
 * Also used to test programmatic registerForAutoconfiguration()
 * that adds additional settings (lifetime, lazy, extra tags).
 */
#[AutoconfigureTag('event.listener')]
interface EventListenerInterface
{
}
