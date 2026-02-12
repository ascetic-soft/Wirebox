<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Marks a class as lazy — a lightweight proxy is returned immediately,
 * and the real instance is created only when a property or method is first accessed.
 *
 * Uses PHP 8.4 native lazy objects (ReflectionClass::newLazyProxy).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Lazy
{
}
