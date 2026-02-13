---
title: API Reference
layout: default
nav_order: 8
---

# API Reference
{: .no_toc }

Complete reference for all public classes and methods.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## ContainerBuilder

The main entry point for configuring and building a DI container.

```php
use AsceticSoft\Wirebox\ContainerBuilder;
```

### Constructor

```php
new ContainerBuilder(string $projectDir)
```

| Parameter | Type | Description |
|:----------|:-----|:------------|
| `$projectDir` | `string` | Base directory for resolving `.env` files |

### Methods

#### `scan(string $directory): void`

Recursively scan a directory and auto-register all concrete classes. Abstract classes, interfaces, traits, and enums are skipped. Classes marked with `#[Exclude]` are also skipped.

```php
$builder->scan(__DIR__ . '/src');
```

If an interface has exactly one implementation, it's auto-bound. Multiple implementations cause an ambiguous binding error (unless resolved with `bind()`).

---

#### `exclude(string $pattern): void`

Exclude files matching a glob pattern from subsequent scans. Patterns are relative to the scanned directory.

```php
$builder->exclude('Entity/*');
$builder->exclude('*Test.php');
```

{: .important }
Must be called **before** `scan()`.

---

#### `bind(string $abstract, string $concrete): void`

Bind an interface or abstract class to a concrete implementation.

```php
$builder->bind(LoggerInterface::class, FileLogger::class);
```

| Parameter | Type | Description |
|:----------|:-----|:------------|
| `$abstract` | `string` | Interface or abstract class FQCN |
| `$concrete` | `string` | Concrete class FQCN |

---

#### `register(string $id, ?Closure $factory = null): Definition`

Register a service by ID, optionally with a factory closure. Returns a `Definition` for fluent configuration.

```php
// With factory
$builder->register(PDO::class, fn($c) => new PDO(...));

// Without factory (for fluent configuration)
$builder->register(Mailer::class)
    ->transient()
    ->tag('mail');
```

| Parameter | Type | Description |
|:----------|:-----|:------------|
| `$id` | `string` | Service identifier (usually FQCN) |
| `$factory` | `?Closure` | Optional factory closure receiving `Container` |

**Returns:** `Definition`

---

#### `parameter(string $name, mixed $value): void`

Define a named parameter. Values can contain `%env(...)%` expressions.

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.name', 'My App');
```

| Parameter | Type | Description |
|:----------|:-----|:------------|
| `$name` | `string` | Parameter name |
| `$value` | `mixed` | Value (plain or with env expressions) |

---

#### `defaultLazy(bool $lazy): void`

Set the default lazy mode. When enabled (default), all services are lazy proxies unless marked `#[Eager]`.

```php
$builder->defaultLazy(false); // Disable default lazy
```

---

#### `registerForAutoconfiguration(string $classOrInterface): AutoconfigureRule`

Register an autoconfiguration rule for an interface or attribute. Returns an `AutoconfigureRule` for fluent configuration.

```php
$builder->registerForAutoconfiguration(EventListenerInterface::class)
    ->tag('event.listener')
    ->singleton()
    ->lazy();
```

| Parameter | Type | Description |
|:----------|:-----|:------------|
| `$classOrInterface` | `string` | Interface or attribute FQCN |

**Returns:** `AutoconfigureRule`

---

#### `build(): Container`

Build and return the runtime container. Validates configuration, detects circular dependencies, and applies default lazy mode.

```php
$container = $builder->build();
```

**Returns:** `Container`

**Throws:** `ContainerException`, `CircularDependencyException`

---

#### `compile(string $outputPath, string $className, string $namespace): void`

Generate a compiled container PHP class. Performs the same validation as `build()`.

```php
$builder->compile(
    outputPath: __DIR__ . '/var/cache/CompiledContainer.php',
    className: 'CompiledContainer',
    namespace: 'App\Cache',
);
```

| Parameter | Type | Description |
|:----------|:-----|:------------|
| `$outputPath` | `string` | File path for generated class |
| `$className` | `string` | Generated class name |
| `$namespace` | `string` | Generated class namespace |

**Throws:** `ContainerException`, `CircularDependencyException`

---

## Container

The runtime dependency injection container. Implements `Psr\Container\ContainerInterface`.

```php
use AsceticSoft\Wirebox\Container;
```

### Methods

#### `get(string $id): mixed`

Resolve and return a service by ID. Singletons are cached after first creation.

```php
$service = $container->get(UserService::class);
```

**Throws:** `NotFoundException`, `AutowireException`, `CircularDependencyException`

---

#### `has(string $id): bool`

Check if a service can be resolved.

```php
if ($container->has(UserService::class)) {
    // ...
}
```

---

#### `getTagged(string $tag): iterable`

Return an iterable of all services with the given tag. Services are resolved lazily as you iterate.

```php
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}
```

| Parameter | Type | Description |
|:----------|:-----|:------------|
| `$tag` | `string` | Tag name |

**Returns:** `iterable<object>`

---

#### `getParameter(string $name): mixed`

Get a parameter value. Environment expressions are resolved on first access.

```php
$host = $container->getParameter('db.host');
```

**Throws:** `ContainerException` (if parameter not found)

---

#### `getParameters(): array`

Get all parameters as an associative array.

```php
$params = $container->getParameters();
// ['db.host' => 'localhost', 'db.port' => 5432, ...]
```

---

## Definition

Fluent builder for configuring a service definition. Returned by `ContainerBuilder::register()`.

```php
use AsceticSoft\Wirebox\Definition;
```

### Methods

#### `singleton(): self`

Configure as singleton (one instance per container). This is the default.

```php
$builder->register(Service::class)->singleton();
```

---

#### `transient(): self`

Configure as transient (new instance on every `get()` call).

```php
$builder->register(Service::class)->transient();
```

---

#### `lazy(): self`

Enable lazy proxy. The real instance is created on first access.

```php
$builder->register(Service::class)->lazy();
```

---

#### `eager(): self`

Disable lazy proxy. The instance is created immediately.

```php
$builder->register(Service::class)->eager();
```

---

#### `tag(string ...$tags): self`

Add one or more tags.

```php
$builder->register(Service::class)->tag('logger', 'audit');
```

---

#### `call(string $method, array $arguments = []): self`

Configure a method call after construction (setter injection).

```php
$builder->register(Service::class)
    ->call('setLogger', [FileLogger::class])
    ->call('setDebug', [true]);
```

| Parameter | Type | Description |
|:----------|:-----|:------------|
| `$method` | `string` | Method name to call |
| `$arguments` | `array` | Arguments (class names resolved, scalars passed as-is) |

---

## AutoconfigureRule

Fluent builder for autoconfiguration rules. Returned by `ContainerBuilder::registerForAutoconfiguration()`.

```php
use AsceticSoft\Wirebox\AutoconfigureRule;
```

### Methods

| Method | Description |
|:-------|:------------|
| `tag(string ...$tags): self` | Add tags to matching services |
| `singleton(): self` | Set matching services as singletons |
| `transient(): self` | Set matching services as transients |
| `lazy(): self` | Enable lazy mode for matching services |
| `eager(): self` | Disable lazy mode for matching services |

---

## Lifetime

Enum for service lifetime.

```php
use AsceticSoft\Wirebox\Lifetime;
```

| Case | Description |
|:-----|:------------|
| `Lifetime::Singleton` | One instance per container |
| `Lifetime::Transient` | New instance every time |

---

## Exceptions

All exceptions are in the `AsceticSoft\Wirebox\Exception` namespace and implement `Psr\Container\ContainerExceptionInterface`.

### NotFoundException

Thrown when a service cannot be found or autowired. Implements `Psr\Container\NotFoundExceptionInterface`.

### AutowireException

Thrown when a constructor parameter cannot be resolved (no type hint, unresolvable type, missing required parameter).

### CircularDependencyException

Thrown when an unsafe circular dependency is detected at build time. The message includes:
- The full cycle path (e.g., `ServiceA -> ServiceB -> ServiceA`)
- Which services are unsafe and why

### ContainerException

General container error. Common causes:
- Ambiguous auto-binding (multiple implementations, no explicit `bind()`)
- Invalid configuration
- Parameter not found

---

## Attributes Summary

All attributes are in the `AsceticSoft\Wirebox\Attribute` namespace.

| Attribute | Target | Repeatable | Description |
|:----------|:-------|:-----------|:------------|
| `#[Singleton]` | Class | No | Singleton lifetime |
| `#[Transient]` | Class | No | Transient lifetime |
| `#[Lazy]` | Class | No | Lazy proxy |
| `#[Eager]` | Class | No | Opt out of lazy |
| `#[Tag('name')]` | Class | Yes | Add tag |
| `#[Inject(Class::class)]` | Parameter | No | Override type hint |
| `#[Param('ENV_VAR')]` | Parameter | No | Inject env variable |
| `#[Exclude]` | Class | No | Skip during scanning |
| `#[AutoconfigureTag('tag')]` | Class | Yes | Auto-tag implementations |
