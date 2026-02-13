<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesAutoconfigure;

use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

/**
 * Custom attribute with #[AutoconfigureTag] — all classes decorated
 * with #[AsScheduled] automatically receive the 'scheduler.task' tag.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[AutoconfigureTag('scheduler.task')]
class AsScheduled
{
}
