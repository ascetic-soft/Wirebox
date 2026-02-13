<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Automatically tags classes that implement the annotated interface
 * or are decorated with the annotated attribute.
 *
 * On an interface — all implementing classes receive the tag:
 *
 *   #[AutoconfigureTag('command.handler')]
 *   interface CommandHandlerInterface {}
 *
 * On a custom attribute — all classes decorated with that attribute receive the tag:
 *
 *   #[Attribute(Attribute::TARGET_CLASS)]
 *   #[AutoconfigureTag('command.handler')]
 *   class AsCommandHandler {}
 *
 * Repeatable: multiple tags can be applied.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AutoconfigureTag
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
