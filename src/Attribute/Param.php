<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Injects a scalar parameter (resolved from env variables) into a constructor parameter.
 *
 * Usage:
 *   public function __construct(
 *       #[Param('DB_HOST')] private string $dbHost,
 *       #[Param('APP_DEBUG')] private bool $debug,
 *   ) {}
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class Param
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
