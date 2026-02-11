<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Autowire;

use AsceticSoft\Wirebox\Attribute\Inject;
use AsceticSoft\Wirebox\Attribute\Param;
use AsceticSoft\Wirebox\Container;
use AsceticSoft\Wirebox\Exception\AutowireException;
use AsceticSoft\Wirebox\Exception\CircularDependencyException;

/**
 * Resolves constructor dependencies via reflection.
 *
 * - Reads type hints to resolve service dependencies
 * - Reads #[Inject] attributes to override type-hinted services
 * - Reads #[Param] attributes to inject scalar parameters from env
 * - Detects circular dependencies
 */
final class Autowirer
{
    /** @var list<string> Stack of currently resolving class names */
    private array $resolvingStack = [];

    /**
     * Create an instance of the given class, resolving all constructor dependencies.
     *
     * @param class-string $className
     */
    public function resolve(string $className, Container $container): object
    {
        // Circular dependency check
        if (in_array($className, $this->resolvingStack, true)) {
            throw new CircularDependencyException([...$this->resolvingStack, $className]);
        }

        $this->resolvingStack[] = $className;

        try {
            $reflectionClass = $this->reflect($className);
            $constructor = $reflectionClass->getConstructor();

            if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
                $instance = $reflectionClass->newInstance();
            } else {
                $arguments = $this->resolveParameters($constructor, $container);
                $instance = $reflectionClass->newInstanceArgs($arguments);
            }

            return $instance;
        } finally {
            array_pop($this->resolvingStack);
        }
    }

    /**
     * Resolve arguments for a method (used for setter injection / method calls).
     *
     * @param list<mixed> $providedArgs Arguments that may contain class-string values to resolve
     * @return list<mixed>
     */
    public function resolveMethodArguments(\ReflectionMethod $method, array $providedArgs, Container $container): array
    {
        $params = $method->getParameters();
        $resolved = [];

        foreach ($params as $index => $param) {
            if (isset($providedArgs[$index])) {
                $arg = $providedArgs[$index];
                // If the argument is a class-string, try to resolve it from the container
                if (is_string($arg) && class_exists($arg)) {
                    $resolved[] = $container->get($arg);
                } else {
                    $resolved[] = $arg;
                }
            } else {
                // Try to autowire from type hint
                $resolved[] = $this->resolveParameter($param, $container);
            }
        }

        return $resolved;
    }

    /**
     * @return list<mixed>
     */
    private function resolveParameters(\ReflectionMethod $constructor, Container $container): array
    {
        $arguments = [];

        foreach ($constructor->getParameters() as $param) {
            $arguments[] = $this->resolveParameter($param, $container);
        }

        return $arguments;
    }

    private function resolveParameter(\ReflectionParameter $param, Container $container): mixed
    {
        // 1. Check for #[Inject] attribute — explicit service override
        $injectAttr = $this->getAttribute($param, Inject::class);
        if ($injectAttr !== null) {
            return $container->get($injectAttr->id);
        }

        // 2. Check for #[Param] attribute — scalar parameter from env
        $paramAttr = $this->getAttribute($param, Param::class);
        if ($paramAttr !== null) {
            $value = $container->getParameter($paramAttr->name);
            if ($value === null) {
                if ($param->isDefaultValueAvailable()) {
                    return $param->getDefaultValue();
                }
                throw new AutowireException(
                    "Parameter \"{$paramAttr->name}\" is not defined for parameter \${$param->getName()} "
                    . "in {$param->getDeclaringClass()?->getName()}::{$param->getDeclaringFunction()->getName()}()"
                );
            }
            return $this->castParameterValue($value, $param);
        }

        // 3. Resolve from type hint
        $type = $param->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if ($container->has($typeName)) {
                return $container->get($typeName);
            }
        }

        // 4. Check for union types — try each type
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType && !$unionType->isBuiltin()) {
                    if ($container->has($unionType->getName())) {
                        return $container->get($unionType->getName());
                    }
                }
            }
        }

        // 5. Default value
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // 6. Nullable — return null
        if ($param->allowsNull()) {
            return null;
        }

        $className = $param->getDeclaringClass()?->getName() ?? 'unknown';
        throw new AutowireException(
            "Cannot resolve parameter \${$param->getName()} in {$className}::{$param->getDeclaringFunction()->getName()}()"
            . ($type instanceof \ReflectionNamedType ? " (type: {$type->getName()})" : '')
        );
    }

    /**
     * Cast a resolved string parameter to the expected type based on the parameter's type hint.
     */
    private function castParameterValue(mixed $value, \ReflectionParameter $param): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $type = $param->getType();
        if (!$type instanceof \ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => json_decode($value, true) ?? [$value],
            default => $value,
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function getAttribute(\ReflectionParameter $param, string $attributeClass): ?object
    {
        $attrs = $param->getAttributes($attributeClass);
        if ($attrs === []) {
            return null;
        }
        return $attrs[0]->newInstance();
    }

    /**
     * @param class-string $className
     * @return \ReflectionClass<object>
     */
    private function reflect(string $className): \ReflectionClass
    {
        if (!class_exists($className)) {
            throw new AutowireException("Class \"{$className}\" does not exist or cannot be reflected.");
        }

        $reflection = new \ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new AutowireException("Class \"{$className}\" is not instantiable (abstract or interface).");
        }

        return $reflection;
    }
}
