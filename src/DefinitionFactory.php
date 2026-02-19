<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

use AsceticSoft\Wirebox\Attribute\Eager as EagerAttr;
use AsceticSoft\Wirebox\Attribute\Exclude;
use AsceticSoft\Wirebox\Attribute\Lazy as LazyAttr;
use AsceticSoft\Wirebox\Attribute\Tag as TagAttr;
use AsceticSoft\Wirebox\Attribute\Transient as TransientAttr;

/**
 * Creates {@see Definition} instances by reading PHP attributes from a class.
 *
 * Centralises the attribute-to-definition mapping that was previously
 * duplicated in ContainerBuilder::scan() and Container::autowireClass().
 */
final class DefinitionFactory
{
    /**
     * Whether the class is marked with #[Exclude].
     *
     * @param \ReflectionClass<object> $ref
     */
    public function isExcluded(\ReflectionClass $ref): bool
    {
        return $ref->getAttributes(Exclude::class) !== [];
    }

    /**
     * Create a Definition by reading class-level PHP attributes.
     *
     * Reads: #[Transient] / #[Singleton], #[Lazy] / #[Eager], #[Tag].
     * Does NOT check #[Exclude] — the caller decides whether to skip.
     *
     * @param \ReflectionClass<object> $ref
     */
    public function createFromAttributes(\ReflectionClass $ref): Definition
    {
        $definition = new Definition(className: $ref->getName());

        if ($ref->getAttributes(TransientAttr::class) !== []) {
            $definition->transient();
        } else {
            $definition->singleton();
        }

        if ($ref->getAttributes(LazyAttr::class) !== []) {
            $definition->lazy();
        } elseif ($ref->getAttributes(EagerAttr::class) !== []) {
            $definition->eager();
        }

        foreach ($ref->getAttributes(TagAttr::class) as $tagAttr) {
            /** @var TagAttr $tag */
            $tag = $tagAttr->newInstance();
            $definition->tag($tag->name);
        }

        return $definition;
    }
}
