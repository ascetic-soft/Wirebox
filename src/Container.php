<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

use AsceticSoft\Wirebox\Attribute\Lazy as LazyAttr;
use AsceticSoft\Wirebox\Attribute\Transient as TransientAttr;
use AsceticSoft\Wirebox\Autowire\Autowirer;
use AsceticSoft\Wirebox\Env\EnvResolver;
use AsceticSoft\Wirebox\Exception\ContainerException;
use AsceticSoft\Wirebox\Exception\NotFoundException;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /** @var array<string, Definition> */
    private array $definitions;

    /** @var array<string, string> Interface/abstract -> concrete class bindings */
    private array $bindings;

    /** @var array<string, object> Singleton instance cache */
    private array $instances = [];

    /** @var array<string, mixed> Resolved parameters */
    private array $parameters;

    /** @var array<string, list<string>> Tag -> list of service IDs */
    private array $tags;

    private readonly Autowirer $autowirer;

    /**
     * @param array<string, Definition> $definitions
     * @param array<string, string> $bindings
     * @param array<string, mixed> $parameters Pre-resolved parameters
     * @param array<string, list<string>> $tags
     */
    public function __construct(
        array $definitions = [],
        array $bindings = [],
        array $parameters = [],
        array $tags = [],
        private readonly ?EnvResolver $envResolver = null,
    ) {
        $this->definitions = $definitions;
        $this->bindings = $bindings;
        $this->parameters = $parameters;
        $this->tags = $tags;
        $this->autowirer = new Autowirer();

        // Register the container itself
        $this->instances[self::class] = $this;
        $this->instances[ContainerInterface::class] = $this;
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        // 1. Check singleton cache
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // 2. Resolve binding (interface -> concrete)
        $resolvedId = $this->resolveBinding($id);

        // Check singleton cache for the resolved ID
        if ($resolvedId !== $id && isset($this->instances[$resolvedId])) {
            return $this->instances[$resolvedId];
        }

        // 3. Get or create definition
        $definition = $this->definitions[$resolvedId] ?? $this->definitions[$id] ?? null;

        // 4. Resolve
        if ($definition !== null) {
            $instance = $this->resolveDefinition($resolvedId, $definition);
        } elseif (\class_exists($resolvedId)) {
            // Auto-wire: class exists but has no explicit definition
            /** @var class-string $resolvedId */
            $instance = $this->autowireClass($resolvedId);
        } else {
            throw new NotFoundException(\sprintf('Service "%s" is not registered and cannot be auto-wired.', $id));
        }

        return $instance;
    }

    public function has(string $id): bool
    {
        if (isset($this->instances[$id]) || isset($this->definitions[$id])) {
            return true;
        }

        $resolvedId = $this->resolveBinding($id);
        if ($resolvedId !== $id) {
            if (isset($this->instances[$resolvedId]) || isset($this->definitions[$resolvedId])) {
                return true;
            }
        }

        // Can be autowired if the class exists and is instantiable
        return \class_exists($resolvedId) && new \ReflectionClass($resolvedId)->isInstantiable();
    }

    /**
     * Get all services tagged with the given tag name.
     *
     * @return iterable<object>
     */
    public function getTagged(string $tag): iterable
    {
        $serviceIds = $this->tags[$tag] ?? [];
        foreach ($serviceIds as $serviceId) {
            $service = $this->get($serviceId);
            if (!\is_object($service)) { // @codeCoverageIgnoreStart
                throw new ContainerException(\sprintf('Service "%s" resolved to a non-object value.', $serviceId));
            } // @codeCoverageIgnoreEnd
            yield $service;
        }
    }

    /**
     * Get a parameter value by name.
     * Returns the raw resolved value (env expressions already resolved by builder).
     */
    public function getParameter(string $name): mixed
    {
        if (\array_key_exists($name, $this->parameters)) {
            return $this->parameters[$name];
        }

        // Try to resolve from env directly
        if ($this->envResolver !== null) {
            $envValue = $this->envResolver->get($name);
            if ($envValue !== null) {
                return $envValue;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, Definition>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, string>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    private function resolveBinding(string $id): string
    {
        return $this->bindings[$id] ?? $id;
    }

    private function resolveDefinition(string $id, Definition $definition): object
    {
        if ($definition->isLazy()) {
            return $this->resolveLazy($id, $definition);
        }

        $instance = $this->createInstance($id, $definition);

        // Cache singleton
        if ($definition->isSingleton()) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Create a lazy proxy that defers real instantiation until first access.
     * Uses PHP 8.4 native ReflectionClass::newLazyProxy().
     */
    private function resolveLazy(string $id, Definition $definition): object
    {
        $className = $definition->getClassName() ?? $id;
        /** @var class-string $className */
        $ref = new \ReflectionClass($className);

        $proxy = $ref->newLazyProxy(fn (): object => $this->createInstance($id, $definition));

        // Cache the proxy itself for singletons
        if ($definition->isSingleton()) {
            $this->instances[$id] = $proxy;
        }

        return $proxy;
    }

    /**
     * Perform the actual instantiation and setter injection for a definition.
     */
    private function createInstance(string $id, Definition $definition): object
    {
        $factory = $definition->getFactory();

        if ($factory !== null) {
            $instance = $factory($this);
        } else {
            $className = $definition->getClassName() ?? $id;
            /** @var class-string $className */
            $instance = $this->autowirer->resolve($className, $this);
        }

        if (!\is_object($instance)) {
            throw new ContainerException(\sprintf('Factory for "%s" must return an object.', $id));
        }

        // Execute method calls (setter injection)
        foreach ($definition->getMethodCalls() as $call) {
            $method = new \ReflectionMethod($instance, $call['method']);
            $args = $this->autowirer->resolveMethodArguments($method, $call['arguments'], $this);
            $method->invokeArgs($instance, $args);
        }

        return $instance;
    }

    /**
     * Auto-wire a class that has no explicit definition.
     * Reads class attributes to determine lifetime and laziness.
     *
     * @param class-string $className
     */
    private function autowireClass(string $className): object
    {
        $ref = new \ReflectionClass($className);
        $isTransient = $ref->getAttributes(TransientAttr::class) !== [];
        $isLazy = $ref->getAttributes(LazyAttr::class) !== [];

        if ($isLazy) {
            $proxy = $ref->newLazyProxy(fn (): object => $this->autowirer->resolve($className, $this));

            if (!$isTransient) {
                $this->instances[$className] = $proxy;
            }

            return $proxy;
        }

        $instance = $this->autowirer->resolve($className, $this);

        if (!$isTransient) {
            $this->instances[$className] = $instance;
        }

        return $instance;
    }
}
