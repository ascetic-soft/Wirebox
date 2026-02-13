---
title: Configuration
layout: default
nav_order: 3
---

# Configuration
{: .no_toc }

Everything you need to configure the Wirebox container.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## ContainerBuilder

The `ContainerBuilder` is the main entry point for configuring the container:

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);
```

The `projectDir` parameter is used as the base path for resolving `.env` files.

---

## Directory Scanning

Scan one or more directories to auto-register all concrete classes. Abstract classes, interfaces, traits, and enums are automatically skipped:

```php
$builder->scan(__DIR__ . '/src');
$builder->scan(__DIR__ . '/modules');
```

The scanner uses PHP's tokenizer for fast, reliable class discovery without loading any files.

### Excluding Files

Exclude files by glob pattern. Patterns are relative to the scanned directory:

```php
$builder->exclude('Entity/*');
$builder->exclude('*Test.php');
$builder->scan(__DIR__ . '/src');
```

{: .important }
Call `exclude()` **before** `scan()` — exclusion patterns apply to subsequent scans.

You can also exclude individual classes with the `#[Exclude]` attribute:

```php
use AsceticSoft\Wirebox\Attribute\Exclude;

#[Exclude]
class InternalHelper
{
    // Will not be registered in the container
}
```

### Auto-binding Interfaces

During scanning, if an interface has **exactly one** implementation in the scanned directories, Wirebox automatically binds the interface to that implementation.

If two or more implementations are found, the binding becomes **ambiguous** and `build()` will throw a `ContainerException`. Resolve ambiguity with an explicit `bind()`:

```php
$builder->scan(__DIR__ . '/Services');
// PaymentInterface has StripePayment and PayPalPayment — ambiguous!
$builder->bind(PaymentInterface::class, StripePayment::class);
```

{: .note }
Interfaces marked with `#[AutoconfigureTag]` are excluded from the ambiguous binding check, since multiple implementations are expected. See [Autoconfiguration]({% link docs/advanced.md %}#autoconfiguration).

---

## Interface Binding

Explicitly bind an interface (or abstract class) to a concrete implementation:

```php
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->bind(CacheInterface::class, RedisCache::class);
```

When resolving a type-hinted dependency, the container checks bindings first, then falls back to concrete class resolution.

---

## Factory Registration

Register a service with a custom factory closure for complex instantiation logic:

```php
use AsceticSoft\Wirebox\Container;

$builder->register(PDO::class, function (Container $c) {
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=app',
            $c->getParameter('db.host'),
            $c->getParameter('db.port'),
        ),
    );
});
```

The closure receives the `Container` instance, so you can resolve other services and parameters inside the factory.

{: .warning }
Factory closures are **not supported** in compiled containers — they require runtime evaluation. Use autowiring with `#[Param]` or `#[Inject]` attributes where possible.

---

## Fluent Definition API

`register()` returns a `Definition` object with a fluent interface for fine-grained control:

```php
$builder->register(FileLogger::class)
    ->transient()                                   // New instance every time
    ->lazy()                                        // Deferred instantiation
    ->tag('logger')                                 // Add a tag
    ->call('setFormatter', [JsonFormatter::class]);  // Setter injection
```

### Available Methods

| Method | Description |
|:-------|:------------|
| `singleton()` | One instance per container (default) |
| `transient()` | New instance on every `get()` call |
| `lazy()` | Return a proxy; real instance created on first access |
| `eager()` | Always create immediately (opt out of default lazy) |
| `tag(string ...$tags)` | Add one or more tags for grouped retrieval |
| `call(string $method, array $args)` | Configure setter injection (called after construction) |

### Setter Injection

Configure methods to be called after the service is constructed:

```php
$builder->register(Mailer::class)
    ->call('setTransport', [SmtpTransport::class])
    ->call('setLogger', [FileLogger::class]);
```

Arguments can be:
- **Class names** — resolved from the container
- **Scalar values** — passed as-is

---

## Parameters

Define parameters that can reference environment variables:

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');
$builder->parameter('rate.limit', '%env(float:RATE_LIMIT)%');
```

### Type Casting

Supported casts inside `%env(...)%` expressions:

| Cast | Example | Result |
|:-----|:--------|:-------|
| `string` (default) | `%env(DB_HOST)%` | `"localhost"` |
| `int` | `%env(int:DB_PORT)%` | `5432` |
| `float` | `%env(float:RATE_LIMIT)%` | `1.5` |
| `bool` | `%env(bool:APP_DEBUG)%` | `true` |

### Embedded Expressions

Environment expressions can be embedded in larger strings:

```php
$builder->parameter('dsn', 'mysql:host=%env(DB_HOST)%;port=%env(DB_PORT)%');
// Result: "mysql:host=localhost;port=5432"
```

### Plain Values

Parameters can also be plain values without env expressions:

```php
$builder->parameter('pagination.limit', 25);
$builder->parameter('app.name', 'My Application');
```

---

## Default Lazy Mode

By default, `ContainerBuilder` enables **lazy mode** — all services are created as lazy proxies unless they have an explicit `#[Eager]` attribute. This is generally what you want for performance.

To disable default lazy mode:

```php
$builder->defaultLazy(false);
```

When disabled, services are created eagerly unless explicitly marked with `#[Lazy]`.

See [Lazy Proxies]({% link docs/advanced.md %}#lazy-proxies) for details.

---

## Building the Container

After configuration, call `build()` to create the runtime container:

```php
$container = $builder->build();
```

The `build()` method:
1. Applies default lazy mode to definitions without explicit settings
2. Detects unsafe circular dependencies
3. Creates and returns a `Container` instance

{: .note }
`build()` validates the configuration and throws exceptions for issues like ambiguous bindings or unsafe circular dependencies. Fix these before deploying.

---

## Next Steps

- [Attributes]({% link docs/attributes.md %}) — Declarative configuration with PHP attributes
- [Environment Variables]({% link docs/environment.md %}) — Dotenv support and priority levels
- [Compiled Container]({% link docs/compiled-container.md %}) — Production optimization
- [Advanced Features]({% link docs/advanced.md %}) — Autoconfiguration, tags, lazy proxies
