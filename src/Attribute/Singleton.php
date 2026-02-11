<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Marks a class as singleton (default behavior, can be used for explicitness).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Singleton
{
}
