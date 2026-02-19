<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

use Psr\Container\ContainerInterface;

/**
 * Extended container contract used by both runtime and compiled containers.
 *
 * Adds tagged-service retrieval and parameter access on top of PSR-11.
 */
interface WireboxContainerInterface extends ContainerInterface
{
    /**
     * Get all services tagged with the given tag name.
     *
     * @return iterable<object>
     */
    public function getTagged(string $tag): iterable;

    /**
     * Get a parameter value by name.
     */
    public function getParameter(string $name): mixed;

    /**
     * Get all resolved parameters.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;
}
