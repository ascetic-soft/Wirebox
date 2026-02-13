<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesAutoconfigure;

use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

/**
 * Interface with #[AutoconfigureTag] — all implementing classes
 * automatically receive the 'command.handler' tag.
 */
#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}
