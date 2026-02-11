<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Specifies a concrete implementation for a type-hinted parameter.
 *
 * Usage on constructor parameters:
 *   public function __construct(
 *       #[Inject(FileLogger::class)] private LoggerInterface $logger,
 *   ) {}
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class Inject
{
    /**
     * @param class-string $id Service ID (usually a class name) to inject.
     */
    public function __construct(
        public readonly string $id,
    ) {
    }
}
