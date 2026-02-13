<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Exception;

class CircularDependencyException extends ContainerException
{
    /**
     * @param list<string> $chain The circular dependency chain (e.g. [A, B, A])
     * @param string $hint Optional explanation of why the cycle is unsafe
     */
    public function __construct(array $chain, string $hint = '')
    {
        $path = \implode(' -> ', $chain);
        $message = "Circular dependency detected: {$path}";
        if ($hint !== '') {
            $message .= ". {$hint}";
        }
        parent::__construct($message);
    }
}
