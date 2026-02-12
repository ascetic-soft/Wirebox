<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Compiler;

use AsceticSoft\Wirebox\Exception\NotFoundException;
use Psr\Container\ContainerInterface;

/**
 * Base class for compiled (generated) containers.
 * No reflection at runtime — each service is a pre-generated factory method.
 */
abstract class CompiledContainer implements ContainerInterface
{
    /** @var array<string, object> Singleton cache */
    protected array $instances = [];

    /** @var array<string, string> Service ID -> factory method name */
    protected array $methodMap = [];

    /** @var array<string, string> Interface -> concrete class bindings */
    protected array $bindings = [];

    /** @var array<string, mixed> Pre-resolved parameters */
    protected array $parameters = [];

    /** @var array<string, list<string>> Tag -> list of service IDs */
    protected array $tags = [];

    public function __construct()
    {
        $this->instances[static::class] = $this;
        $this->instances[ContainerInterface::class] = $this;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Resolve binding
        $resolvedId = $this->bindings[$id] ?? $id;
        if ($resolvedId !== $id && isset($this->instances[$resolvedId])) {
            return $this->instances[$resolvedId];
        }

        $method = $this->methodMap[$resolvedId] ?? $this->methodMap[$id] ?? null;
        if ($method === null) {
            throw new NotFoundException(\sprintf('Service "%s" is not registered in the compiled container.', $id));
        }

        return $this->{$method}();
    }

    public function has(string $id): bool
    {
        if (isset($this->instances[$id]) || isset($this->methodMap[$id])) {
            return true;
        }

        $resolvedId = $this->bindings[$id] ?? $id;
        return isset($this->instances[$resolvedId]) || isset($this->methodMap[$resolvedId]);
    }

    /**
     * @return iterable<object>
     */
    public function getTagged(string $tag): iterable
    {
        $serviceIds = $this->tags[$tag] ?? [];
        foreach ($serviceIds as $serviceId) {
            $service = $this->get($serviceId);
            if (!\is_object($service)) { // @codeCoverageIgnoreStart
                throw new NotFoundException(\sprintf('Service "%s" resolved to a non-object value.', $serviceId));
            } // @codeCoverageIgnoreEnd
            yield $service;
        }
    }

    public function getParameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
