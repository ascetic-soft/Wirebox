<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Excludes a class from auto-registration during directory scanning.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Exclude
{
}
