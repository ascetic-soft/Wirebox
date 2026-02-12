<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

use AsceticSoft\Wirebox\Attribute\Exclude;
use AsceticSoft\Wirebox\Attribute\Tag as TagAttr;
use AsceticSoft\Wirebox\Attribute\Transient as TransientAttr;
use AsceticSoft\Wirebox\Compiler\ContainerCompiler;
use AsceticSoft\Wirebox\Env\EnvResolver;
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

    /** @var list<string> Glob patterns to exclude from scanning */
    private array $excludePatterns = [];

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

            // Read #[Tag] attributes
            $tagAttrs = $ref->getAttributes(TagAttr::class);
            foreach ($tagAttrs as $tagAttr) {
                /** @var TagAttr $tag */
                $tag = $tagAttr->newInstance();
                $definition->tag($tag->name);
            }

            $this->definitions[$className] = $definition;

            // Register tags
            foreach ($definition->getTags() as $tagName) {
                $this->tags[$tagName][] = $className;
            }

            // Auto-bind: if the class implements exactly one interface, bind it
            $interfaces = $ref->getInterfaceNames();
            foreach ($interfaces as $interface) {
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
     * Register a service definition.
     *
     * @param class-string $id
     * @param (\Closure(Container): mixed)|null $factory
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

        $resolvedParams = $this->resolveParameters();

        return new Container(
            definitions: $this->definitions,
            bindings: $this->bindings,
            parameters: $resolvedParams,
            tags: $this->tags,
            envResolver: $this->envResolver,
        );
    }

    /**
     * Compile the container to a PHP file for production use.
     */
    public function compile(string $outputPath, string $className = 'CompiledContainer', string $namespace = ''): void
    {
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
     * Resolve all parameter values, expanding %env(...)% expressions.
     *
     * @return array<string, mixed>
     */
    private function resolveParameters(): array
    {
        $resolved = [];
        foreach ($this->parameters as $name => $value) {
            $resolved[$name] = $this->envResolver->resolveParameter($value);
        }
        return $resolved;
    }
}
