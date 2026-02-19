<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;
use AsceticSoft\Wirebox\Compiler\ContainerCompiler;
use AsceticSoft\Wirebox\Env\EnvResolver;
use AsceticSoft\Wirebox\Scanner\ClassScanner;
use AsceticSoft\Wirebox\Validator\CircularDependencyDetector;

final class ContainerBuilder
{
    /** @var array<string, Definition> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $parameters = [];

    /** @var array<string, list<string>> Tag -> list of service IDs */
    private array $tags = [];

    /** @var array<class-string, AutoconfigureRule> Target class/interface/attribute -> rule */
    private array $autoconfiguration = [];

    /** @var list<string> Glob patterns to exclude from scanning */
    private array $excludePatterns = [];

    /** Whether services should be lazy by default (proxy returned, real instance created on first access) */
    private bool $defaultLazy = true;

    private readonly ClassScanner $scanner;

    private readonly EnvResolver $envResolver;

    private readonly DefinitionFactory $definitionFactory;

    private readonly CircularDependencyDetector $circularDependencyDetector;

    private readonly AutoBindingResolver $autoBindingResolver;

    public function __construct(
        private readonly string $projectDir,
        ?ClassScanner $scanner = null,
        ?EnvResolver $envResolver = null,
        ?DefinitionFactory $definitionFactory = null,
        ?CircularDependencyDetector $circularDependencyDetector = null,
        ?AutoBindingResolver $autoBindingResolver = null,
    ) {
        $this->scanner = $scanner ?? new ClassScanner();
        $this->envResolver = $envResolver ?? new EnvResolver($this->projectDir);
        $this->definitionFactory = $definitionFactory ?? new DefinitionFactory();
        $this->circularDependencyDetector = $circularDependencyDetector ?? new CircularDependencyDetector();
        $this->autoBindingResolver = $autoBindingResolver ?? new AutoBindingResolver();
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

            if (!\class_exists($className)) {
                continue;
            }
            $ref = new \ReflectionClass($className);

            if ($this->definitionFactory->isExcluded($ref)) {
                continue;
            }

            $definition = $this->definitionFactory->createFromAttributes($ref);

            // Apply autoconfiguration rules
            $this->applyAutoconfiguration($ref, $definition);

            $this->definitions[$className] = $definition;

            // Register tags
            foreach ($definition->getTags() as $tagName) {
                $this->tags[$tagName][] = $className;
            }

            // Auto-bind interfaces
            $this->autoBindingResolver->registerImplementation(
                $className,
                $ref->getInterfaceNames(),
                $this->autoconfiguration,
            );
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
        $this->autoBindingResolver->exclude(...$classOrInterface);

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
        $this->autoBindingResolver->bind($abstract, $concrete);
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
        $this->autoBindingResolver->validateNoAmbiguity();

        $bindings = $this->autoBindingResolver->getBindings();

        $this->resolveDefaultLazy();
        $this->circularDependencyDetector->detect($this->definitions, $bindings);
        $resolvedParams = $this->resolveParameters();

        return new Container(
            definitions: $this->definitions,
            bindings: $bindings,
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
        $this->autoBindingResolver->validateNoAmbiguity();

        $bindings = $this->autoBindingResolver->getBindings();

        $this->resolveDefaultLazy();
        $this->circularDependencyDetector->detect($this->definitions, $bindings);

        $compiler = new ContainerCompiler();
        $compiler->compile(
            definitions: $this->definitions,
            bindings: $bindings,
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
        return $this->autoBindingResolver->getBindings();
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
