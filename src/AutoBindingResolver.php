<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

/**
 * Tracks interface-to-implementation bindings discovered during directory scanning.
 *
 * Detects ambiguous bindings (multiple implementations for the same interface)
 * and provides validation before the container is built.
 */
final class AutoBindingResolver
{
    /** @var array<string, string> Interface -> concrete class */
    private array $bindings = [];

    /** @var array<string, list<string>> Interface -> implementations (tracked when ambiguous) */
    private array $ambiguousBindings = [];

    /** @var array<class-string, true> Interfaces excluded from auto-binding */
    private array $excludedFromAutoBinding = [];

    /**
     * Register an implementation for the interfaces of a scanned class.
     *
     * @param list<string>                          $interfaces
     * @param array<class-string, AutoconfigureRule> $autoconfiguration
     */
    public function registerImplementation(
        string $className,
        array $interfaces,
        array $autoconfiguration,
    ): void {
        foreach ($interfaces as $interface) {
            if ($this->isExcluded($interface, $autoconfiguration)) {
                continue;
            }

            if (\interface_exists($interface) && new \ReflectionClass($interface)->isInternal()) {
                continue;
            }

            if (isset($this->ambiguousBindings[$interface])) {
                $this->ambiguousBindings[$interface][] = $className;
            } elseif (!isset($this->bindings[$interface])) {
                $this->bindings[$interface] = $className;
            } elseif ($this->bindings[$interface] !== $className) {
                $this->ambiguousBindings[$interface] = [
                    $this->bindings[$interface],
                    $className,
                ];
                unset($this->bindings[$interface]);
            }
        }
    }

    /**
     * Mark an explicit binding, resolving any ambiguity for that interface.
     *
     * @param class-string $abstract
     * @param class-string $concrete
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->ambiguousBindings[$abstract]);
    }

    /**
     * Exclude interfaces from the ambiguous auto-binding check.
     *
     * @param class-string ...$classOrInterface
     */
    public function exclude(string ...$classOrInterface): void
    {
        foreach ($classOrInterface as $item) {
            $this->excludedFromAutoBinding[$item] = true;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Validate that no ambiguous auto-bindings remain.
     *
     * @throws Exception\ContainerException when an unresolved ambiguous binding is found
     */
    public function validateNoAmbiguity(): void
    {
        if ($this->ambiguousBindings === []) {
            return;
        }

        $interface = \array_key_first($this->ambiguousBindings);
        $implementations = $this->ambiguousBindings[$interface];

        throw new Exception\ContainerException(\sprintf(
            'Ambiguous auto-binding for interface "%s": found implementations "%s". Use explicit bind() to resolve the ambiguity.',
            $interface,
            \implode('", "', $implementations),
        ));
    }

    /**
     * Check whether an interface should be excluded from the ambiguous
     * auto-binding check.
     *
     * @param array<class-string, AutoconfigureRule> $autoconfiguration
     */
    private function isExcluded(string $interface, array $autoconfiguration): bool
    {
        if (isset($this->excludedFromAutoBinding[$interface])) {
            return true;
        }

        if (isset($autoconfiguration[$interface])) {
            return true;
        }

        if (\interface_exists($interface)) {
            $ref = new \ReflectionClass($interface);
            if ($ref->getAttributes(AutoconfigureTag::class) !== []) {
                return true;
            }
        }

        return false;
    }
}
