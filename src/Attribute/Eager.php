<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Attribute;

/**
 * Marks a class as eager (not lazy) — the real instance is created immediately.
 *
 * Use this attribute to opt out of lazy instantiation when the container's
 * default lazy mode is enabled.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Eager
{
}
