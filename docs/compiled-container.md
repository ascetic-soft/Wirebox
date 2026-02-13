---
title: Compiled Container
layout: default
nav_order: 6
---

# Compiled Container
{: .no_toc }

Zero-reflection production container for maximum performance.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Overview

In development, Wirebox uses reflection to resolve dependencies. This is fast enough for development but adds overhead in production. The **compiled container** generates a plain PHP class with dedicated factory methods for each service — **zero reflection at runtime**.

---

## Generating the Compiled Container

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);

// Configure as usual
$builder->exclude('Entity/*');
$builder->scan(__DIR__ . '/src');
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');

// Generate compiled container
$builder->compile(
    outputPath: __DIR__ . '/var/cache/CompiledContainer.php',
    className: 'CompiledContainer',
    namespace: 'App\Cache',
);
```

### Parameters

| Parameter | Description |
|:----------|:------------|
| `outputPath` | File path for the generated PHP class |
| `className` | Name of the generated class |
| `namespace` | PHP namespace for the generated class |

---

## Using the Compiled Container

In production, require the generated file and instantiate it directly:

```php
require_once __DIR__ . '/var/cache/CompiledContainer.php';

$container = new App\Cache\CompiledContainer();

// Use exactly like the runtime container
$service = $container->get(UserService::class);
$loggers = $container->getTagged('logger');
$host = $container->getParameter('db.host');
```

The compiled container implements `Psr\Container\ContainerInterface` and supports the same API as the runtime container.

---

## What Gets Compiled

The generated class includes:

- **Factory methods** — A dedicated `create_*()` method for each service
- **Singleton caching** — Services are cached after first creation
- **Bindings map** — Interface-to-implementation mappings
- **Parameters** — All defined parameters with resolved env expressions
- **Tags** — Tagged service groups for `getTagged()`
- **Lazy proxies** — Deferred instantiation using `ReflectionClass::newLazyProxy()`
- **Setter injection** — Method calls configured via `call()`

---

## Example: Generated Code

For a simple setup:

```php
$builder->scan(__DIR__ . '/src');
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->parameter('db.host', '%env(DB_HOST)%');
```

The compiler generates something like:

```php
namespace App\Cache;

use AsceticSoft\Wirebox\Compiler\CompiledContainer;

class CompiledContainer extends \AsceticSoft\Wirebox\Compiler\CompiledContainer
{
    protected function getBindings(): array
    {
        return [
            \App\LoggerInterface::class => \App\FileLogger::class,
        ];
    }

    protected function getParameterDefinitions(): array
    {
        return [
            'db.host' => '%env(DB_HOST)%',
        ];
    }

    protected function getTagMap(): array
    {
        return [];
    }

    protected function createAppFileLogger(): \App\FileLogger
    {
        return new \App\FileLogger();
    }

    protected function createAppUserService(): \App\UserService
    {
        return new \App\UserService(
            $this->get(\App\LoggerInterface::class),
        );
    }
}
```

---

## Development vs Production Workflow

### Recommended Setup

```php
// bootstrap.php
use AsceticSoft\Wirebox\ContainerBuilder;

$compiledPath = __DIR__ . '/var/cache/CompiledContainer.php';

if (file_exists($compiledPath)) {
    // Production: use compiled container
    require_once $compiledPath;
    $container = new App\Cache\CompiledContainer();
} else {
    // Development: use runtime container
    $builder = new ContainerBuilder(projectDir: __DIR__);
    $builder->scan(__DIR__ . '/src');
    $builder->bind(LoggerInterface::class, FileLogger::class);
    $builder->parameter('db.host', '%env(DB_HOST)%');
    $container = $builder->build();
}
```

### Build Script

Create a build script for deployment:

```php
// bin/compile-container.php
use AsceticSoft\Wirebox\ContainerBuilder;

require_once __DIR__ . '/../vendor/autoload.php';

$builder = new ContainerBuilder(projectDir: dirname(__DIR__));
$builder->scan(dirname(__DIR__) . '/src');
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->parameter('db.host', '%env(DB_HOST)%');

$builder->compile(
    outputPath: dirname(__DIR__) . '/var/cache/CompiledContainer.php',
    className: 'CompiledContainer',
    namespace: 'App\Cache',
);

echo "Container compiled successfully.\n";
```

Run during deployment:

```bash
php bin/compile-container.php
```

---

## Limitations

{: .warning }
**Factory closures are not supported** in compiled containers. Closures cannot be serialized to PHP code. Use autowiring with attributes instead.

```php
// This will NOT be included in the compiled container
$builder->register(PDO::class, function ($c) {
    return new PDO(...);
});

// Use this instead
class PdoFactory
{
    public function __construct(
        #[Param('DB_HOST')] private string $host,
        #[Param('DB_PORT')] private int $port,
    ) {
    }

    public function create(): PDO
    {
        return new PDO("mysql:host={$this->host};port={$this->port}");
    }
}
```

---

## Lazy Proxies in Compiled Containers

Lazy proxies are fully supported in compiled containers. The generated factory methods use `ReflectionClass::newLazyProxy()`:

```php
protected function createAppHeavyService(): \App\HeavyService
{
    $ref = new \ReflectionClass(\App\HeavyService::class);
    return $ref->newLazyProxy(function () {
        return new \App\HeavyService(
            $this->get(\App\Database::class),
        );
    });
}
```

The proxy behaves identically to the runtime container's lazy behavior.

---

## Best Practices

1. **Always regenerate** the compiled container when services change
2. **Add to `.gitignore`** — don't commit the generated file:
   ```
   /var/cache/CompiledContainer.php
   ```
3. **Compile during deployment** — add to your CI/CD pipeline:
   ```bash
   php bin/compile-container.php
   composer dump-env prod
   ```
4. **Validate before compiling** — `compile()` runs the same checks as `build()`, including circular dependency detection
5. **Share configuration** — extract builder configuration into a function or config file to avoid duplication between development and compilation
