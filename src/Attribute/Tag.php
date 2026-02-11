<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Tags a class for grouped retrieval via Container::getTagged().
 *
 * Repeatable: a class can have multiple tags.
 *
 *   #[Tag('event.listener')]
 *   #[Tag('logger')]
 *   class MyService {}
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Tag
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
