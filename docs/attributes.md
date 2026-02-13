---
title: Attributes
layout: default
nav_order: 4
---

# PHP Attributes
{: .no_toc }

Declarative service configuration using PHP 8.4 native attributes.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Overview

Wirebox provides a set of PHP attributes to configure services declaratively — right in the class definition. No external configuration files needed.

| Attribute | Target | Description |
|:----------|:-------|:------------|
| `#[Singleton]` | Class | One instance per container (default) |
| `#[Transient]` | Class | New instance on every `get()` |
| `#[Lazy]` | Class | Deferred instantiation via proxy |
| `#[Eager]` | Class | Opt out of default lazy mode |
| `#[Tag]` | Class | Tag for grouped retrieval (repeatable) |
| `#[Inject]` | Parameter | Override type-hinted service |
| `#[Param]` | Parameter | Inject env variable or parameter |
| `#[Exclude]` | Class | Skip during directory scanning |
| `#[AutoconfigureTag]` | Class | Auto-tag by interface or attribute |

All attributes are in the `AsceticSoft\Wirebox\Attribute` namespace.

---

## #[Singleton]

Marks a class as a singleton. Since this is the default behavior, use it primarily for explicitness:

```php
use AsceticSoft\Wirebox\Attribute\Singleton;

#[Singleton]
class DatabaseConnection
{
    public function __construct(
        #[Param('DB_HOST')] private string $host,
    ) {
    }
}
```

**Equivalent fluent API:**

```php
$builder->register(DatabaseConnection::class)->singleton();
```

---

## #[Transient]

A new instance is created on every `get()` call:

```php
use AsceticSoft\Wirebox\Attribute\Transient;

#[Transient]
class RequestContext
{
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }
}
```

Each call to `$container->get(RequestContext::class)` returns a fresh instance.

**Equivalent fluent API:**

```php
$builder->register(RequestContext::class)->transient();
```

{: .tip }
Use `#[Transient]` for services that hold request-specific state, such as request context, form data, or DTOs.

---

## #[Lazy]

Return a lightweight proxy immediately; the real instance is created only when a property or method is first accessed. Uses PHP 8.4 native lazy objects (`ReflectionClass::newLazyProxy`):

```php
use AsceticSoft\Wirebox\Attribute\Lazy;

#[Lazy]
class HeavyReportGenerator
{
    public function __construct(
        private PDO $db,
        private CacheInterface $cache,
    ) {
        // expensive setup — only runs when actually needed
    }
}
```

**Key characteristics:**
- The proxy is a real instance of the class (passes `instanceof` checks)
- Construction is deferred until first property or method access
- Fully supported by the compiled container
- Can be combined with `#[Transient]` for a new lazy proxy on every `get()`

**Equivalent fluent API:**

```php
$builder->register(HeavyReportGenerator::class)->lazy();
```

{: .note }
When `defaultLazy` is enabled (the default), **all** services are lazy unless marked with `#[Eager]`. The `#[Lazy]` attribute is only needed when `defaultLazy` is off.

---

## #[Eager]

Opt out of lazy instantiation when the container's default lazy mode is enabled:

```php
use AsceticSoft\Wirebox\Attribute\Eager;

#[Eager]
class AppConfig
{
    public function __construct()
    {
        // Always created immediately, even when defaultLazy is on
    }
}
```

Use this for services that:
- Must be initialized early (configuration, event subscribers)
- Have side effects in the constructor
- Need to validate settings at startup

**Equivalent fluent API:**

```php
$builder->register(AppConfig::class)->eager();
```

---

## #[Tag]

Tag a class for grouped retrieval. The attribute is **repeatable** — a class can have multiple tags:

```php
use AsceticSoft\Wirebox\Attribute\Tag;

#[Tag('event.listener')]
#[Tag('audit')]
class UserCreatedListener
{
    public function handle(object $event): void
    {
        // ...
    }
}
```

Retrieve all services with a specific tag:

```php
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}
```

**Equivalent fluent API:**

```php
$builder->register(UserCreatedListener::class)->tag('event.listener', 'audit');
```

{: .tip }
Tags are perfect for plugin systems, event dispatchers, middleware chains, and command/query bus patterns.

---

## #[AutoconfigureTag]

Automatically tag all classes that implement an interface or are decorated with a custom attribute. Place `#[AutoconfigureTag]` on the **interface** or **attribute** class itself.

### On an Interface

All implementing classes automatically receive the tag:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

// Automatically tagged as 'command.handler' when scanned
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void
    {
        // ...
    }
}
```

### On a Custom Attribute

All classes decorated with that attribute receive the tag:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
#[AutoconfigureTag('scheduler.task')]
class AsScheduled {}

// Automatically tagged as 'scheduler.task' when scanned
#[AsScheduled]
class DailyReportTask
{
    public function run(): void { /* ... */ }
}
```

### Multiple Tags

The attribute is repeatable:

```php
#[AutoconfigureTag('command.handler')]
#[AutoconfigureTag('auditable')]
interface CommandHandlerInterface {}
```

{: .note }
Interfaces with `#[AutoconfigureTag]` are excluded from ambiguous auto-binding checks, since multiple implementations are expected.

See also [Autoconfiguration]({{ '/docs/advanced.html' | relative_url }}#autoconfiguration) for programmatic autoconfiguration.

---

## #[Inject]

Override the type-hinted service for a specific constructor parameter:

```php
use AsceticSoft\Wirebox\Attribute\Inject;

class NotificationService
{
    public function __construct(
        #[Inject(SmtpMailer::class)]
        private MailerInterface $mailer,
    ) {
    }
}
```

Without `#[Inject]`, Wirebox would resolve `MailerInterface` via the container's bindings. With `#[Inject]`, it always injects `SmtpMailer` regardless of bindings.

**Use cases:**
- When you need a specific implementation for one service but a different one elsewhere
- When there's no global binding for the interface
- When you want to override the global binding for a specific consumer

---

## #[Param]

Inject a scalar value from environment variables directly into a constructor parameter:

```php
use AsceticSoft\Wirebox\Attribute\Param;

class DatabaseService
{
    public function __construct(
        #[Param('DB_HOST')] private string $host,
        #[Param('DB_PORT')] private int $port,
        #[Param('APP_DEBUG')] private bool $debug = false,
    ) {
    }
}
```

**Type casting** is automatic based on the parameter's type hint:

| PHP Type | Environment Value | Result |
|:---------|:-----------------|:-------|
| `string` | `"localhost"` | `"localhost"` |
| `int` | `"5432"` | `5432` |
| `float` | `"1.5"` | `1.5` |
| `bool` | `"true"` / `"1"` | `true` |
| `bool` | `"false"` / `"0"` / `""` | `false` |

{: .tip }
`#[Param]` reads directly from environment variables (using the 3-level priority system). It's the simplest way to inject configuration values.

See [Environment Variables]({{ '/docs/environment.html' | relative_url }}) for details on the resolution order.

---

## #[Exclude]

Exclude a class from auto-registration during directory scanning:

```php
use AsceticSoft\Wirebox\Attribute\Exclude;

#[Exclude]
class InternalHelper
{
    // Will not be registered in the container
}
```

**Use cases:**
- Helper/utility classes that shouldn't be services
- Base classes not meant for direct instantiation
- Test doubles accidentally in the scanned directory

{: .note }
`#[Exclude]` only affects directory scanning. You can still manually register an excluded class with `$builder->register()`.

---

## Combining Attributes

Attributes can be freely combined:

```php
use AsceticSoft\Wirebox\Attribute\{Lazy, Singleton, Tag, Param};

#[Singleton]
#[Lazy]
#[Tag('event.listener')]
class OrderEventListener
{
    public function __construct(
        #[Param('NOTIFICATION_EMAIL')]
        private string $notifyEmail,
    ) {
    }

    public function onOrderCreated(OrderCreated $event): void
    {
        // ...
    }
}
```

---

## Attribute vs Fluent API

Every attribute has an equivalent fluent API call. Use whichever style you prefer:

| Attribute | Fluent API |
|:----------|:-----------|
| `#[Singleton]` | `->singleton()` |
| `#[Transient]` | `->transient()` |
| `#[Lazy]` | `->lazy()` |
| `#[Eager]` | `->eager()` |
| `#[Tag('x')]` | `->tag('x')` |
| `#[Exclude]` | `$builder->exclude(...)` |

{: .tip }
**Attributes** are best for configuration that belongs with the class definition. **Fluent API** is best for external or conditional configuration (e.g., different bindings per environment).
