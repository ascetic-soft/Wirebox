<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;
use AsceticSoft\Wirebox\Attribute\Eager as EagerAttr;
use AsceticSoft\Wirebox\Attribute\Exclude;
use AsceticSoft\Wirebox\Attribute\Inject as InjectAttr;
use AsceticSoft\Wirebox\Attribute\Lazy as LazyAttr;
use AsceticSoft\Wirebox\Attribute\Param as ParamAttr;
use AsceticSoft\Wirebox\Attribute\Tag as TagAttr;
use AsceticSoft\Wirebox\Attribute\Transient as TransientAttr;
use AsceticSoft\Wirebox\Compiler\ContainerCompiler;
use AsceticSoft\Wirebox\Env\EnvResolver;
use AsceticSoft\Wirebox\Exception\CircularDependencyException;
use AsceticSoft\Wirebox\Scanner\ClassScanner;

final class ContainerBuilder
{
    /** @var array<string, Definition> */
    private array $definitions = [];

    /** @var array<string, string> Interface/abstract -> concrete class */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $parameters = [];

    /** @var array<string, list<string>> Tag -> list of service IDs */
    private array $tags = [];

    /** @var array<string, list<string>> Interface -> implementations (tracked when ambiguous) */
    private array $ambiguousBindings = [];

    /** @var array<class-string, AutoconfigureRule> Target class/interface/attribute -> rule */
    private array $autoconfiguration = [];

    /** @var list<string> Glob patterns to exclude from scanning */
    private array $excludePatterns = [];

    /** @var array<class-string, true> Interfaces excluded from auto-binding */
    private array $excludedFromAutoBinding = [];

    /** Whether services should be lazy by default (proxy returned, real instance created on first access) */
    private bool $defaultLazy = true;

    private readonly ClassScanner $scanner;

    private readonly EnvResolver $envResolver;

    public function __construct(
        private readonly string $projectDir,
    ) {
        $this->scanner = new ClassScanner();
        $this->envResolver = new EnvResolver($this->projectDir);
    }

    /**
     * Scan a directory for classes and auto-register them.
     * Skips abstract classes, interfaces, traits, enums, and classes with #[Exclude].
     *
     * Auto-binds interfaces that have exactly one implementation.
     * If multiple implementations are found for the same interface,
     * the binding is marked as ambiguous and build() will throw
     * a ContainerException unless resolved with an explicit bind().
     *
     * Autoconfigured interfaces (via #[AutoconfigureTag] or
     * registerForAutoconfiguration()) and interfaces excluded via
     * excludeFromAutoBinding() are excluded from the ambiguous binding
     * check — they are expected to have multiple implementations.
     */
    public function scan(string $directory): self
    {
        $classes = $this->scanner->scan($directory, $this->excludePatterns);

        foreach ($classes as $className) {
            // Skip already registered
            if (isset($this->definitions[$className])) {
                continue;
            }

            // Check for #[Exclude] attribute
            if (!\class_exists($className)) {
                continue;
            }
            $ref = new \ReflectionClass($className);

            if ($ref->getAttributes(Exclude::class) !== []) {
                continue;
            }

            $definition = new Definition(className: $className);

            // Read #[Transient] / #[Singleton] attributes
            if ($ref->getAttributes(TransientAttr::class) !== []) {
                $definition->transient();
            } else {
                $definition->singleton();
            }

            // Read #[Lazy] / #[Eager] attributes
            if ($ref->getAttributes(LazyAttr::class) !== []) {
                $definition->lazy();
            } elseif ($ref->getAttributes(EagerAttr::class) !== []) {
                $definition->eager();
            }

            // Read #[Tag] attributes
            $tagAttrs = $ref->getAttributes(TagAttr::class);
            foreach ($tagAttrs as $tagAttr) {
                /** @var TagAttr $tag */
                $tag = $tagAttr->newInstance();
                $definition->tag($tag->name);
            }

            // Apply autoconfiguration rules
            $this->applyAutoconfiguration($ref, $definition);

            $this->definitions[$className] = $definition;

            // Register tags
            foreach ($definition->getTags() as $tagName) {
                $this->tags[$tagName][] = $className;
            }

            // Auto-bind: if the class implements exactly one interface, bind it.
            // Autoconfigured and explicitly excluded interfaces are skipped —
            // they are expected to have multiple implementations.
            $interfaces = $ref->getInterfaceNames();
            foreach ($interfaces as $interface) {
                if ($this->isExcludedFromAutoBinding($interface)) {
                    continue;
                }
                // Skip built-in PHP interfaces (Throwable, Stringable, etc.)
                // — they are never useful as DI bindings.
                if (new \ReflectionClass($interface)->isInternal()) {
                    continue;
                }
                // Only auto-bind if no explicit binding exists
                if (isset($this->ambiguousBindings[$interface])) {
                    // Already marked as ambiguous — just add another implementation
                    $this->ambiguousBindings[$interface][] = $className;
                } elseif (!isset($this->bindings[$interface])) {
                    $this->bindings[$interface] = $className;
                } elseif ($this->bindings[$interface] !== $className) {
                    // Second implementation found — mark as ambiguous
                    $this->ambiguousBindings[$interface] = [
                        $this->bindings[$interface],
                        $className,
                    ];
                    unset($this->bindings[$interface]);
                }
            }
        }

        return $this;
    }

    /**
     * Add a glob pattern to exclude from directory scanning.
     */
    public function exclude(string $pattern): self
    {
        $this->excludePatterns[] = $pattern;
        return $this;
    }

    /**
     * Exclude an interface from the ambiguous auto-binding check.
     *
     * Use this when multiple classes implement the same interface
     * and you don't want the builder to treat it as an ambiguous binding.
     * Unlike registerForAutoconfiguration(), this does not apply any
     * autoconfiguration rules — it only suppresses the ambiguity error.
     *
     * @param class-string ...$classOrInterface
     */
    public function excludeFromAutoBinding(string ...$classOrInterface): self
    {
        foreach ($classOrInterface as $item) {
            $this->excludedFromAutoBinding[$item] = true;
        }

        return $this;
    }

    /**
     * Set the default lazy behavior for all services.
     *
     * When enabled, all services without an explicit lazy/eager setting
     * will be created as lazy proxies (real instance is deferred until first access).
     *
     * Enabled by default. Use defaultLazy(false) to disable.
     */
    public function defaultLazy(bool $lazy = true): self
    {
        $this->defaultLazy = $lazy;
        return $this;
    }

    /**
     * Register an autoconfiguration rule for services that implement
     * the given interface or are decorated with the given attribute.
     *
     * During scan(), any class that implements $classOrInterface (if it is
     * an interface) or is decorated with $classOrInterface (if it is an
     * attribute) will have the returned rule applied to its definition.
     *
     * @param class-string $classOrInterface
     */
    public function registerForAutoconfiguration(string $classOrInterface): AutoconfigureRule
    {
        return $this->autoconfiguration[$classOrInterface] ??= new AutoconfigureRule();
    }

    /**
     * Register a service definition.
     *
     * @param class-string $id
     */
    public function register(string $id, ?\Closure $factory = null): Definition
    {
        if (isset($this->definitions[$id])) {
            $definition = $this->definitions[$id];
            if ($factory !== null) {
                $definition->setFactory($factory);
            }
            return $definition;
        }

        $definition = new Definition(className: $id, factory: $factory);
        $this->definitions[$id] = $definition;

        return $definition;
    }

    /**
     * Bind an interface/abstract to a concrete implementation.
     *
     * @param class-string $abstract
     * @param class-string $concrete
     */
    public function bind(string $abstract, string $concrete): self
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->ambiguousBindings[$abstract]);
        return $this;
    }

    /**
     * Set a parameter value.
     * Supports %env(VAR_NAME)% expressions that are resolved at build time.
     */
    public function parameter(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Build the container.
     */
    public function build(): Container
    {
        if ($this->ambiguousBindings !== []) {
            $interface = \array_key_first($this->ambiguousBindings);
            $implementations = $this->ambiguousBindings[$interface];

            throw new Exception\ContainerException(\sprintf(
                'Ambiguous auto-binding for interface "%s": found implementations "%s". Use explicit bind() to resolve the ambiguity.',
                $interface,
                \implode('", "', $implementations),
            ));
        }

        $this->resolveDefaultLazy();
        $this->detectUnsafeCircularDependencies();
        $resolvedParams = $this->resolveParameters();

        return new Container(
            definitions: $this->definitions,
            bindings: $this->bindings,
            parameters: $resolvedParams,
            tags: $this->tags,
            envResolver: $this->envResolver,
            defaultLazy: $this->defaultLazy,
        );
    }

    /**
     * Compile the container to a PHP file for production use.
     */
    public function compile(string $outputPath, string $className = 'CompiledContainer', string $namespace = ''): void
    {
        $this->resolveDefaultLazy();
        $this->detectUnsafeCircularDependencies();

        $compiler = new ContainerCompiler();
        $compiler->compile(
            definitions: $this->definitions,
            bindings: $this->bindings,
            parameters: $this->resolveParameters(),
            tags: $this->tags,
            outputPath: $outputPath,
            className: $className,
            namespace: $namespace,
        );
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
     * Detect circular dependencies that would be unsafe at runtime.
     *
     * Circular dependencies are only safe when **all** services in the cycle
     * are lazy singletons — the cached proxy breaks the cycle before real
     * instantiation begins.
     *
     * Unsafe cycles (will throw {@see CircularDependencyException}):
     *  - Any **eager** service in the cycle — the Autowirer will encounter
     *    the same class on the resolving stack and throw at runtime.
     *  - Any **lazy transient** service — the proxy is not cached, so every
     *    resolution creates a new proxy and re-enters instantiation,
     *    leading to infinite recursion.
     *
     * Factory-based definitions are skipped because their dependencies
     * cannot be determined statically.
     *
     * @throws CircularDependencyException when an unsafe cycle is found
     */
    private function detectUnsafeCircularDependencies(): void
    {
        $graph = $this->buildDependencyGraph();

        /** @var array<string, bool> Nodes whose sub-tree is fully processed */
        $done = [];
        /** @var array<string, bool> Nodes currently on the DFS path */
        $onPath = [];
        /** @var list<string> Ordered DFS path for cycle extraction */
        $path = [];

        foreach (\array_keys($graph) as $node) {
            if (!isset($done[$node])) {
                $this->dfsDetectCycle($node, $graph, $onPath, $done, $path);
            }
        }
    }

    /**
     * Build an adjacency list representing service dependencies.
     *
     * Analyses constructor parameters (respecting #[Inject] and #[Param])
     * and explicit method-call arguments registered via {@see Definition::call()}.
     *
     * @return array<string, list<string>> service ID → list of dependency IDs
     */
    private function buildDependencyGraph(): array
    {
        $graph = [];

        foreach ($this->definitions as $id => $definition) {
            $className = $definition->getClassName() ?? $id;

            // Factory definitions cannot be analysed statically
            if ($definition->getFactory() !== null || !\class_exists($className)) {
                $graph[$id] = [];
                continue;
            }

            $deps = [];
            $ref = new \ReflectionClass($className);

            // --- Constructor parameters ---
            $constructor = $ref->getConstructor();
            if ($constructor !== null) {
                foreach ($constructor->getParameters() as $param) {
                    $depId = $this->resolveParameterDependency($param);
                    if ($depId !== null) {
                        $deps[] = $depId;
                    }
                }
            }

            // --- Method-call arguments (setter injection) ---
            foreach ($definition->getMethodCalls() as $call) {
                foreach ($call['arguments'] as $arg) {
                    if (\is_string($arg)) {
                        $resolved = $this->bindings[$arg] ?? $arg;
                        if (isset($this->definitions[$resolved])) {
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
     */
    private function resolveParameterDependency(\ReflectionParameter $param): ?string
    {
        // #[Param] — scalar parameter, not a service dependency
        if ($param->getAttributes(ParamAttr::class) !== []) {
            return null;
        }

        // #[Inject] — explicit service override
        $injectAttrs = $param->getAttributes(InjectAttr::class);
        if ($injectAttrs !== []) {
            /** @var InjectAttr $inject */
            $inject = $injectAttrs[0]->newInstance();
            $depId = $this->bindings[$inject->id] ?? $inject->id;

            return isset($this->definitions[$depId]) ? $depId : null;
        }

        // Named type hint
        $type = $param->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $depId = $this->bindings[$type->getName()] ?? $type->getName();

            return isset($this->definitions[$depId]) ? $depId : null;
        }

        // Union type — take the first resolvable branch
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType && !$unionType->isBuiltin()) {
                    $depId = $this->bindings[$unionType->getName()] ?? $unionType->getName();
                    if (isset($this->definitions[$depId])) {
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
     * @param array<string, bool>         $onPath Nodes on the current DFS path
     * @param array<string, bool>         $done   Fully processed nodes
     * @param list<string>                $path   Current path for cycle extraction
     *
     * @throws CircularDependencyException
     */
    private function dfsDetectCycle(
        string $node,
        array $graph,
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
                // Extract the cycle from the path
                $cycleStart = \array_search($dep, $path, true);
                if ($cycleStart === false) {
                    throw new \LogicException("Cycle start not found for dependency '$dep'.");
                }
                $cycle = \array_slice($path, $cycleStart);
                $cycle[] = $dep; // close the loop

                $this->validateCycle($cycle);
                continue;
            }

            $this->dfsDetectCycle($dep, $graph, $onPath, $done, $path);
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
     * @param list<string> $cycle Closed cycle path (last element = first element)
     *
     * @throws CircularDependencyException
     */
    private function validateCycle(array $cycle): void
    {
        $problems = [];
        // Remove the closing duplicate for inspection
        $serviceIds = \array_slice($cycle, 0, -1);

        foreach ($serviceIds as $id) {
            $def = $this->definitions[$id];
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

    /**
     * Apply the default lazy setting to all definitions
     * that don't have an explicit lazy/eager flag.
     */
    private function resolveDefaultLazy(): void
    {
        foreach ($this->definitions as $definition) {
            if (!$definition->hasExplicitLazy()) {
                $definition->lazy($this->defaultLazy);
            }
        }
    }

    /**
     * Resolve all parameter values, expanding %env(...)% expressions.
     *
     * @return array<string, mixed>
     */
    private function resolveParameters(): array
    {
        return array_map(fn ($value) => $this->envResolver->resolveParameter($value), $this->parameters);
    }

    /**
     * Check whether an interface should be excluded from the ambiguous
     * auto-binding check: explicitly excluded via excludeFromAutoBinding(),
     * autoconfigured programmatically, or decorated with #[AutoconfigureTag].
     */
    private function isExcludedFromAutoBinding(string $interface): bool
    {
        // 1. Explicitly excluded via excludeFromAutoBinding()
        if (isset($this->excludedFromAutoBinding[$interface])) {
            return true;
        }

        // 2. Programmatic: registered via registerForAutoconfiguration()
        if (isset($this->autoconfiguration[$interface])) {
            return true;
        }

        // 3. Declarative: interface has #[AutoconfigureTag]
        if (\interface_exists($interface)) {
            $ref = new \ReflectionClass($interface);
            if ($ref->getAttributes(AutoconfigureTag::class) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply autoconfiguration rules to a definition based on
     * the class's interfaces and attributes.
     *
     * Three sources of autoconfiguration:
     * 1. Programmatic rules registered via registerForAutoconfiguration()
     * 2. #[AutoconfigureTag] on interfaces the class implements
     * 3. #[AutoconfigureTag] on attribute classes that decorate the class
     *
     * @param \ReflectionClass<object> $ref
     */
    private function applyAutoconfiguration(\ReflectionClass $ref, Definition $definition): void
    {
        // 1. Programmatic autoconfiguration rules
        foreach ($this->autoconfiguration as $target => $rule) {
            // Check if the class implements the target interface
            if (\interface_exists($target) && $ref->implementsInterface($target)) {
                $rule->apply($definition);
            }
            // Check if the class has the target attribute
            if ($ref->getAttributes($target) !== []) {
                $rule->apply($definition);
            }
        }

        // 2. Declarative #[AutoconfigureTag] on interfaces
        foreach ($ref->getInterfaces() as $iface) {
            foreach ($iface->getAttributes(AutoconfigureTag::class) as $attr) {
                $definition->tag($attr->newInstance()->name);
            }
        }

        // 3. Declarative #[AutoconfigureTag] on custom attributes
        foreach ($ref->getAttributes() as $classAttr) {
            $attrRef = new \ReflectionClass($classAttr->getName());
            foreach ($attrRef->getAttributes(AutoconfigureTag::class) as $attr) {
                $definition->tag($attr->newInstance()->name);
            }
        }
    }
}
