# Wirebox

[![CI](https://github.com/ascetic-soft/Wirebox/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/Wirebox/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/Wirebox/graph/badge.svg?token=yotFHWiMtP)](https://codecov.io/gh/ascetic-soft/Wirebox)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)
[![Total Downloads](https://img.shields.io/packagist/dt/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/wirebox/php)](https://packagist.org/packages/ascetic-soft/wirebox)
[![License](https://img.shields.io/packagist/l/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)

Lightweight PHP 8.4 DI container with autowiring, directory scanning, PHP attributes, and dotenv support.

📖 **[Full Documentation](https://ascetic-soft.github.io/Wirebox/)** | **[Документация на русском](https://ascetic-soft.github.io/Wirebox/ru/)** | **[中文文档](https://ascetic-soft.github.io/Wirebox/zh/)**

## Features

- **PSR-11** compatible (`Psr\Container\ContainerInterface`)
- **Autowiring** — automatic constructor dependency resolution via reflection
- **Directory scanning** — point at a directory, all classes are auto-registered
- **PHP Attributes** — `#[Inject]`, `#[Singleton]`, `#[Transient]`, `#[Lazy]`, `#[Eager]`, `#[Tag]`, `#[Param]`, `#[Exclude]`, `#[AutoconfigureTag]`
- **Autoconfiguration** — automatically tag services by interface or attribute (Symfony-style)
- **Dotenv** — built-in `.env` parser with 3-level priority (no external dependencies)
- **Tagged services** — group services by tag and retrieve them as a collection
- **Lazy proxies** — deferred instantiation via PHP 8.4 native lazy objects
- **Compiled container** — generate a PHP class with zero reflection at runtime
- **Setter injection** — configure method calls on services after instantiation
- **Circular dependency detection** — unsafe cycles detected at build time with clear error messages

## Requirements

- PHP >= 8.4
- [psr/container](https://packagist.org/packages/psr/container) ^2.0

## Installation

```bash
composer require ascetic-soft/wirebox
```

## Quick Start

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);

// Scan a directory — all concrete classes are auto-registered
$builder->scan(__DIR__ . '/src');

// Build the container
$container = $builder->build();

// Resolve any service
$service = $container->get(App\UserService::class);
```

## Configuration

### ContainerBuilder

The `ContainerBuilder` is the main entry point for configuring the container.

```php
$builder = new ContainerBuilder(projectDir: __DIR__);
```

The `projectDir` is used as the base path for resolving `.env` files.

### Directory Scanning

Scan directories to auto-register all concrete classes (abstract classes, interfaces, traits, and enums are skipped):

```php
$builder->scan(__DIR__ . '/src');
$builder->scan(__DIR__ . '/modules');
```

Exclude files by glob pattern:

```php
$builder->exclude('Entity/*');
$builder->exclude('*Test.php');
$builder->scan(__DIR__ . '/src');
```

Classes marked with `#[Exclude]` are skipped automatically:

```php
use AsceticSoft\Wirebox\Attribute\Exclude;

#[Exclude]
class InternalHelper
{
    // Will not be registered in the container
}
```

### Interface Binding

Bind an interface to a concrete implementation:

```php
$builder->bind(LoggerInterface::class, FileLogger::class);
```

When scanning, if an interface has exactly one implementation in the scanned directory, it is auto-bound automatically.
If two or more implementations of the same interface are found, the auto-binding becomes ambiguous and `build()` will throw a `ContainerException`. Use an explicit `bind()` call to resolve the ambiguity:

```php
$builder->scan(__DIR__ . '/Services');
// PaymentInterface has StripePayment and PayPalPayment — ambiguous!
$builder->bind(PaymentInterface::class, StripePayment::class);
```

Alternatively, if you don't need a specific binding but want to suppress the ambiguity error (e.g. the interface is resolved at runtime, or you only use tagged iteration), use `excludeFromAutoBinding()`:

```php
$builder->excludeFromAutoBinding(PaymentInterface::class);
$builder->scan(__DIR__ . '/Services');
// No error — PaymentInterface is excluded from the auto-binding check
$container = $builder->build();
```

Multiple interfaces can be excluded at once:

```php
$builder->excludeFromAutoBinding(
    PaymentInterface::class,
    NotificationChannelInterface::class,
);
```

> **Note:** Unlike `registerForAutoconfiguration()`, this does not apply any autoconfiguration rules (tags, lifetime, etc.) — it only suppresses the ambiguity error.

### Factory Registration

Register a service with a custom factory closure:

```php
$builder->register(Connection::class, function (Container $c) {
    return new Connection(
        host: $c->getParameter('db.host'),
        port: $c->getParameter('db.port'),
    );
});
```

### Fluent Definition API

Override or configure individual service definitions:

```php
$builder->register(FileLogger::class)
    ->transient()                                   // New instance every time
    ->lazy()                                        // Deferred instantiation
    ->tag('logger')                                 // Add a tag
    ->call('setFormatter', [JsonFormatter::class]);  // Setter injection
```

### Parameters and Environment Variables

Set parameters that can reference environment variables:

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');
$builder->parameter('rate.limit', '%env(float:RATE_LIMIT)%');
```

Supported type casts: `string` (default), `int`, `float`, `bool`.

Parameters can also contain env expressions embedded in a larger string:

```php
$builder->parameter('dsn', 'mysql:host=%env(DB_HOST)%;port=%env(DB_PORT)%');
```

## Environment Variables

Wirebox resolves environment variables with 3-level priority (highest first):

| Priority | Source               | Description                                                               |
|----------|----------------------|---------------------------------------------------------------------------|
| 1        | `.env.local.php`     | Generated by `composer dump-env`. A PHP file returning an array. Fastest. |
| 2        | `$_ENV` / `getenv()` | Real system environment variables.                                        |
| 3        | `.env`               | Parsed by built-in `DotEnvParser`. Development fallback.                  |

All files are resolved relative to `projectDir`.

### `.env` File Format

```env
APP_NAME=Wirebox
DB_HOST=localhost
DB_PORT=5432
APP_DEBUG=true

# Comments are supported
QUOTED="hello world"
SINGLE='literal value'

# Variable interpolation
BASE_PATH=/opt
FULL_PATH="${BASE_PATH}/app"

# Export prefix is stripped
export SECRET_KEY=abc123
```

### Production: `composer dump-env`

For production, use Symfony's `composer dump-env` to generate `.env.local.php`:

```bash
composer dump-env prod
```

This creates a PHP file that returns an array — no file parsing at runtime.

## PHP Attributes

### `#[Singleton]`

Marks a class as singleton (this is the default behavior, use for explicitness):

```php
use AsceticSoft\Wirebox\Attribute\Singleton;

#[Singleton]
class DatabaseConnection
{
}
```

### `#[Transient]`

A new instance is created on every `get()` call:

```php
use AsceticSoft\Wirebox\Attribute\Transient;

#[Transient]
class RequestContext
{
}
```

### `#[Lazy]`

Return a lightweight proxy immediately; the real instance is created only when a property or method is first accessed. Uses PHP 8.4 native lazy objects (`ReflectionClass::newLazyProxy`):

```php
use AsceticSoft\Wirebox\Attribute\Lazy;

#[Lazy]
class HeavyReportGenerator
{
    public function __construct(
        private Connection $db,
        private CacheInterface $cache,
    ) {
        // expensive setup...
    }
}
```

Can be combined with `#[Transient]` to create a new lazy proxy on every `get()` call.

The same behavior is available via the fluent API:

```php
$builder->register(HeavyReportGenerator::class)->lazy();
```

Lazy proxies are fully supported by the compiled container.

#### Default lazy mode

`ContainerBuilder` enables lazy mode by default — all services without an explicit `#[Lazy]` or `#[Eager]` attribute are created as lazy proxies. You can disable this:

```php
$builder->defaultLazy(false);
```

### `#[Eager]`

Opt out of lazy instantiation when the container's default lazy mode is enabled:

```php
use AsceticSoft\Wirebox\Attribute\Eager;

#[Eager]
class AppConfig
{
    // Always created immediately, even when defaultLazy is on
}
```

The same behavior is available via the fluent API:

```php
$builder->register(AppConfig::class)->eager();
```

### `#[Tag]`

Tag a class for grouped retrieval. Repeatable:

```php
use AsceticSoft\Wirebox\Attribute\Tag;

#[Tag('event.listener')]
#[Tag('audit')]
class UserCreatedListener
{
}
```

Retrieve tagged services:

```php
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}
```

### `#[AutoconfigureTag]`

Automatically tag all classes that implement an interface or are decorated with a custom attribute. Place `#[AutoconfigureTag]` on the interface or attribute class.

On an **interface** — all implementing classes receive the tag:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

// Automatically receives the 'command.handler' tag when scanned
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void
    {
        // ...
    }
}
```

On a **custom attribute** — all classes decorated with that attribute receive the tag:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
#[AutoconfigureTag('scheduler.task')]
class AsScheduled {}

// Automatically receives the 'scheduler.task' tag when scanned
#[AsScheduled]
class DailyReportTask
{
    public function run(): void { /* ... */ }
}
```

Repeatable — multiple tags can be applied:

```php
#[AutoconfigureTag('command.handler')]
#[AutoconfigureTag('auditable')]
interface CommandHandlerInterface {}
```

### Programmatic Autoconfiguration

For more control (lifetime, lazy, multiple tags), use `registerForAutoconfiguration()` on the builder:

```php
$builder->registerForAutoconfiguration(EventListenerInterface::class)
    ->tag('event.listener')
    ->singleton()
    ->lazy();
```

Any class implementing `EventListenerInterface` found during `scan()` will automatically get the `event.listener` tag, be configured as a singleton, and use lazy proxies.

This also works with attributes:

```php
$builder->registerForAutoconfiguration(AsScheduled::class)
    ->tag('scheduler.task')
    ->transient();
```

### CQRS Example

Autoconfiguration makes it easy to set up command and query handlers:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

#[AutoconfigureTag('query.handler')]
interface QueryHandlerInterface
{
    public function __invoke(object $query): mixed;
}

// Handlers — no manual tagging needed
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

class DeleteUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

class GetUserHandler implements QueryHandlerInterface
{
    public function __invoke(object $query): mixed { /* ... */ }
}
```

Build and retrieve. Autoconfigured interfaces are excluded from the ambiguous auto-binding check, so multiple implementations work seamlessly:

```php
$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');

// No need for bind() — CommandHandlerInterface is autoconfigured
$container = $builder->build();

// Iterate all command handlers
foreach ($container->getTagged('command.handler') as $handler) {
    // CreateUserHandler, DeleteUserHandler
}

// Iterate all query handlers
foreach ($container->getTagged('query.handler') as $handler) {
    // GetUserHandler
}
```

### `#[Inject]`

Specify a concrete implementation for a type-hinted parameter:

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

### `#[Param]`

Inject a scalar value from environment variables:

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

The value is automatically cast to the parameter's type hint (`string`, `int`, `float`, `bool`).

### `#[Exclude]`

Exclude a class from auto-registration during directory scanning:

```php
use AsceticSoft\Wirebox\Attribute\Exclude;

#[Exclude]
class InternalHelper
{
}
```

## Container API

### PSR-11

```php
$service = $container->get(UserService::class);
$exists  = $container->has(UserService::class);
```

### Tagged Services

```php
$loggers = $container->getTagged('logger'); // iterable<object>
```

### Parameters

```php
$host = $container->getParameter('db.host');
$all  = $container->getParameters();
```

### Self-Resolution

The container registers itself under three keys, so you can type-hint whichever you prefer:

```php
use Psr\Container\ContainerInterface;
use AsceticSoft\Wirebox\WireboxContainerInterface;

class ServiceLocator
{
    public function __construct(
        // Any of the three works:
        private ContainerInterface $psr,              // PSR-11
        private WireboxContainerInterface $wirebox,   // Wirebox extended contract
    ) {
    }
}
```

`WireboxContainerInterface` extends PSR-11 with `getTagged()`, `getParameter()`, and `getParameters()`. Both `Container` and `CompiledContainer` implement it.

## Compiled Container

For production, compile the container to a PHP class. This eliminates reflection at runtime:

```php
$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->parameter('db.host', '%env(DB_HOST)%');

// Generate the compiled container
$builder->compile(
    outputPath: __DIR__ . '/var/cache/CompiledContainer.php',
    className: 'CompiledContainer',
    namespace: 'App\Cache',
);
```

Use the compiled container in production:

```php
require_once __DIR__ . '/var/cache/CompiledContainer.php';

$container = new App\Cache\CompiledContainer();
$service = $container->get(UserService::class);
```

The compiled container:
- Extends `AsceticSoft\Wirebox\Compiler\CompiledContainer`
- Implements `WireboxContainerInterface` (and PSR-11 `ContainerInterface`)
- Has a dedicated factory method for each service
- Supports singleton caching, interface bindings, parameters, and tags
- Binding aliases are folded into the method map at compile time for single-lookup resolution
- Does **not** support factory closures (they require runtime evaluation)

## Circular Dependencies

Wirebox detects circular dependencies **at build time** (`build()` / `compile()`) and throws `CircularDependencyException` for unsafe cycles before the container is ever used.

### When is a cycle safe?

A circular dependency is safe **only** when **all** services in the cycle are **lazy singletons**. The proxy is cached before real instantiation begins, so when the dependency chain loops back, it finds the proxy in the cache instead of re-entering construction:

```php
// Safe — both are lazy singletons (the default)
#[Lazy]
class ServiceA
{
    public function __construct(public readonly ServiceB $b) {}
}

#[Lazy]
class ServiceB
{
    public function __construct(public readonly ServiceA $a) {}
}

$container = $builder->build(); // OK
$a = $container->get(ServiceA::class);
assert($a->b->a === $a); // same proxy
```

### When is a cycle unsafe?

| Scenario                          | Result                                              |
|-----------------------------------|-----------------------------------------------------|
| All services are lazy singletons  | Safe — proxy cached before instantiation            |
| Any service is **eager**          | Unsafe — Autowirer hits the same class twice         |
| Any service is **lazy transient** | Unsafe — proxy is not cached, infinite recursion     |

Unsafe cycles are reported with a clear message:

```
Circular dependency detected: ServiceA -> ServiceB -> ServiceA.
All services in a circular dependency must be lazy singletons.
Unsafe: ServiceB (not lazy)
```

> **Note:** Factory-based definitions (`register(..., fn() => ...)`) are skipped during cycle analysis because their dependencies cannot be determined statically.

## Error Handling

Wirebox throws specific exceptions for common issues:

| Exception                     | When                                                    |
|-------------------------------|---------------------------------------------------------|
| `NotFoundException`           | Service not found and cannot be auto-wired              |
| `AutowireException`           | Cannot resolve a constructor parameter                  |
| `CircularDependencyException` | Unsafe circular dependency detected at build or runtime |
| `ContainerException`          | General container error (e.g. ambiguous bindings)       |

All exceptions implement `Psr\Container\ContainerExceptionInterface`.

```php
use AsceticSoft\Wirebox\Exception\CircularDependencyException;

try {
    $builder->build();
} catch (CircularDependencyException $e) {
    // "Circular dependency detected: ServiceA -> ServiceB -> ServiceA. ..."
    echo $e->getMessage();
}
```

## Full Example

```php
// bootstrap.php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);

// Exclude entities and migrations from the container
$builder->exclude('Entity/*');
$builder->exclude('Migration/*');

// Scan application classes
$builder->scan(__DIR__ . '/src');

// Explicit bindings where needed
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->bind(CacheInterface::class, RedisCache::class);

// Environment-based parameters
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');

// Custom factory
$builder->register(PDO::class, function ($c) {
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=app', 
            $c->getParameter('db.host'),
            $c->getParameter('db.port'),
        ),
    );
});

// Build and use
$container = $builder->build();
$app = $container->get(App\Kernel::class);
$app->run();
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
