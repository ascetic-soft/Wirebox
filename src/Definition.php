<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

/**
 * Describes how to create and configure a service.
 *
 * Supports fluent API:
 *   $definition->transient()->tag('logger')->call('setFormatter', [JsonFormatter::class]);
 */
final class Definition
{
    private Lifetime $lifetime = Lifetime::Singleton;

    private bool $lazy = false;

    /** @var list<string> */
    private array $tags = [];

    /** @var list<array{method: string, arguments: list<mixed>}> */
    private array $methodCalls = [];

    /**
     * @param class-string|null $className
     * @param (\Closure(Container): mixed)|null $factory
     */
    public function __construct(
        private ?string $className = null,
        private ?\Closure $factory = null,
    ) {
    }

    // --- Fluent setters ---

    public function singleton(): self
    {
        $this->lifetime = Lifetime::Singleton;
        return $this;
    }

    public function transient(): self
    {
        $this->lifetime = Lifetime::Transient;
        return $this;
    }

    public function lifetime(Lifetime $lifetime): self
    {
        $this->lifetime = $lifetime;
        return $this;
    }

    /**
     * Mark this service as lazy — a proxy is returned immediately,
     * and the real instance is created only on first access.
     */
    public function lazy(bool $lazy = true): self
    {
        $this->lazy = $lazy;
        return $this;
    }

    public function tag(string ...$tags): self
    {
        foreach ($tags as $tag) {
            if (!\in_array($tag, $this->tags, true)) {
                $this->tags[] = $tag;
            }
        }
        return $this;
    }

    /**
     * Register a method call (setter injection).
     *
     * @param list<mixed> $arguments
     */
    public function call(string $method, array $arguments = []): self
    {
        $this->methodCalls[] = compact('method', 'arguments');
        return $this;
    }

    /**
     * @param class-string $className
     */
    public function setClassName(string $className): self
    {
        $this->className = $className;
        return $this;
    }

    public function setFactory(\Closure $factory): self
    {
        $this->factory = $factory;
        return $this;
    }

    // --- Getters ---

    /**
     * @return class-string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getFactory(): ?\Closure
    {
        return $this->factory;
    }

    public function getLifetime(): Lifetime
    {
        return $this->lifetime;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return list<array{method: string, arguments: list<mixed>}>
     */
    public function getMethodCalls(): array
    {
        return $this->methodCalls;
    }

    public function isSingleton(): bool
    {
        return $this->lifetime === Lifetime::Singleton;
    }

    public function isLazy(): bool
    {
        return $this->lazy;
    }
}
