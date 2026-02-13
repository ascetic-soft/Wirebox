<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

/**
 * Describes autoconfiguration rules to apply to matching services.
 *
 * Returned by ContainerBuilder::registerForAutoconfiguration().
 * Supports fluent API:
 *   $builder->registerForAutoconfiguration(SomeInterface::class)
 *       ->tag('my.tag')
 *       ->singleton()
 *       ->lazy();
 */
final class AutoconfigureRule
{
    /** @var list<string> */
    private array $tags = [];

    private ?Lifetime $lifetime = null;

    private ?bool $lazy = null;

    public function tag(string ...$tags): self
    {
        foreach ($tags as $tag) {
            if (!\in_array($tag, $this->tags, true)) {
                $this->tags[] = $tag;
            }
        }

        return $this;
    }

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

    public function lazy(bool $lazy = true): self
    {
        $this->lazy = $lazy;

        return $this;
    }

    /**
     * Apply this rule's settings to a service definition.
     * Only overrides settings that were explicitly configured in the rule.
     */
    public function apply(Definition $definition): void
    {
        $definition->tag(...$this->tags);

        if ($this->lifetime !== null) {
            $definition->lifetime($this->lifetime);
        }

        if ($this->lazy !== null) {
            $definition->lazy($this->lazy);
        }
    }
}
