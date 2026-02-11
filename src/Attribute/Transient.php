<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Marks a class as transient — a new instance is created on every resolve.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Transient
{
}
