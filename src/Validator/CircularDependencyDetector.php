<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Validator;

use AsceticSoft\Wirebox\Attribute\Inject as InjectAttr;
use AsceticSoft\Wirebox\Attribute\Param as ParamAttr;
use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Exception\CircularDependencyException;

/**
 * Detects circular dependencies that would be unsafe at runtime.
 *
 * Circular dependencies are only safe when **all** services in the cycle
 * are lazy singletons — the cached proxy breaks the cycle before real
 * instantiation begins.
 *
 * Unsafe cycles:
 *  - Any **eager** service in the cycle — the Autowirer will encounter
 *    the same class on the resolving stack and throw at runtime.
 *  - Any **lazy transient** service — the proxy is not cached, so every
 *    resolution creates a new proxy and re-enters instantiation,
 *    leading to infinite recursion.
 *
 * Factory-based definitions are skipped because their dependencies
 * cannot be determined statically.
 */
final class CircularDependencyDetector
{
    /**
     * @param array<string, Definition> $definitions
     * @param array<string, string>     $bindings
     *
     * @throws CircularDependencyException when an unsafe cycle is found
     */
    public function detect(array $definitions, array $bindings): void
    {
        $graph = $this->buildDependencyGraph($definitions, $bindings);

        /** @var array<string, bool> $done Nodes whose sub-tree is fully processed */
        $done = [];
        /** @var array<string, bool> $onPath Nodes currently on the DFS path */
        $onPath = [];
        /** @var list<string> $path Ordered DFS path for cycle extraction */
        $path = [];

        foreach (\array_keys($graph) as $node) {
            if (!isset($done[$node])) {
                $this->dfsDetectCycle($node, $graph, $definitions, $onPath, $done, $path);
            }
        }
    }

    /**
     * Build an adjacency list representing service dependencies.
     *
     * Analyses constructor parameters (respecting #[Inject] and #[Param])
     * and explicit method-call arguments registered via {@see Definition::call()}.
     *
     * @param array<string, Definition> $definitions
     * @param array<string, string>     $bindings
     *
     * @return array<string, list<string>> service ID -> list of dependency IDs
     */
    private function buildDependencyGraph(array $definitions, array $bindings): array
    {
        $graph = [];

        foreach ($definitions as $id => $definition) {
            $className = $definition->getClassName() ?? $id;

            if ($definition->getFactory() !== null || !\class_exists($className)) {
                $graph[$id] = [];
                continue;
            }

            $deps = [];
            $ref = new \ReflectionClass($className);

            $constructor = $ref->getConstructor();
            if ($constructor !== null) {
                foreach ($constructor->getParameters() as $param) {
                    $depId = $this->resolveParameterDependency($param, $definitions, $bindings);
                    if ($depId !== null) {
                        $deps[] = $depId;
                    }
                }
            }

            foreach ($definition->getMethodCalls() as $call) {
                foreach ($call['arguments'] as $arg) {
                    if (\is_string($arg)) {
                        $resolved = $bindings[$arg] ?? $arg;
                        if (isset($definitions[$resolved])) {
                            $deps[] = $resolved;
                        }
                    }
                }
            }

            $graph[$id] = \array_values(\array_unique($deps));
        }

        return $graph;
    }

    /**
     * Resolve the service ID that a constructor parameter depends on.
     *
     * Returns `null` when the parameter is a scalar (#[Param]), has no
     * type hint, or its type does not correspond to a registered service.
     *
     * @param array<string, Definition> $definitions
     * @param array<string, string>     $bindings
     */
    private function resolveParameterDependency(
        \ReflectionParameter $param,
        array $definitions,
        array $bindings,
    ): ?string {
        if ($param->getAttributes(ParamAttr::class) !== []) {
            return null;
        }

        $injectAttrs = $param->getAttributes(InjectAttr::class);
        if ($injectAttrs !== []) {
            /** @var InjectAttr $inject */
            $inject = $injectAttrs[0]->newInstance();
            $depId = $bindings[$inject->id] ?? $inject->id;

            return isset($definitions[$depId]) ? $depId : null;
        }

        $type = $param->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $depId = $bindings[$type->getName()] ?? $type->getName();

            return isset($definitions[$depId]) ? $depId : null;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType && !$unionType->isBuiltin()) {
                    $depId = $bindings[$unionType->getName()] ?? $unionType->getName();
                    if (isset($definitions[$depId])) {
                        return $depId;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Depth-first search that throws on the first unsafe cycle.
     *
     * @param array<string, list<string>> $graph
     * @param array<string, Definition>   $definitions
     * @param array<string, bool>         $onPath Nodes on the current DFS path
     * @param array<string, bool>         $done   Fully processed nodes
     * @param list<string>                $path   Current path for cycle extraction
     *
     * @throws CircularDependencyException
     */
    private function dfsDetectCycle(
        string $node,
        array $graph,
        array $definitions,
        array &$onPath,
        array &$done,
        array &$path,
    ): void {
        $onPath[$node] = true;
        $path[] = $node;

        foreach ($graph[$node] as $dep) {
            if (isset($done[$dep])) {
                continue;
            }

            if (isset($onPath[$dep])) {
                $cycleStart = \array_search($dep, $path, true);
                if ($cycleStart === false) {
                    throw new \LogicException("Cycle start not found for dependency '$dep'.");
                }
                $cycle = \array_slice($path, $cycleStart);
                $cycle[] = $dep;

                $this->validateCycle($cycle, $definitions);
                continue;
            }

            $this->dfsDetectCycle($dep, $graph, $definitions, $onPath, $done, $path);
        }

        \array_pop($path);
        unset($onPath[$node]);
        $done[$node] = true;
    }

    /**
     * Check whether every service in the cycle is a lazy singleton.
     *
     * If any service is eager or transient, the cycle is unsafe and
     * a {@see CircularDependencyException} is thrown with a detailed hint.
     *
     * @param list<string>              $cycle       Closed cycle path (last element = first element)
     * @param array<string, Definition> $definitions
     *
     * @throws CircularDependencyException
     */
    private function validateCycle(array $cycle, array $definitions): void
    {
        $problems = [];
        $serviceIds = \array_slice($cycle, 0, -1);

        foreach ($serviceIds as $id) {
            $def = $definitions[$id];
            $reasons = [];

            if (!$def->isLazy()) {
                $reasons[] = 'not lazy';
            }
            if (!$def->isSingleton()) {
                $reasons[] = 'not a singleton';
            }

            if ($reasons !== []) {
                $short = \substr(\strrchr($id, '\\') ?: $id, 1) ?: $id;
                $problems[] = \sprintf('%s (%s)', $short, \implode(', ', $reasons));
            }
        }

        if ($problems !== []) {
            throw new CircularDependencyException(
                $cycle,
                'All services in a circular dependency must be lazy singletons. Unsafe: '
                . \implode('; ', $problems),
            );
        }
    }
}
