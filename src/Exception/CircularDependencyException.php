<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Exception;

class CircularDependencyException extends ContainerException
{
    /**
     * @param list<string> $chain
     */
    public function __construct(array $chain)
    {
        $path = \implode(' -> ', $chain);
        parent::__construct("Circular dependency detected: {$path}");
    }
}
