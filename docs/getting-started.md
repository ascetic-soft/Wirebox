---
title: Getting Started
layout: default
nav_order: 2
---

# Getting Started
{: .no_toc }

Get up and running with Wirebox in under 5 minutes.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Installation

Install Wirebox via Composer:

```bash
composer require ascetic-soft/wirebox
```

**Requirements:**
- PHP >= 8.4
- psr/container ^2.0

---

## Your First Container

### Step 1: Create your services

```php
// src/Mailer.php
namespace App;

class Mailer
{
    public function send(string $to, string $message): void
    {
        // send email...
    }
}
```

```php
// src/UserService.php
namespace App;

class UserService
{
    public function __construct(
        private Mailer $mailer,
    ) {
    }

    public function register(string $email): void
    {
        // create user...
        $this->mailer->send($email, 'Welcome!');
    }
}
```

### Step 2: Build the container

```php
// bootstrap.php
use AsceticSoft\Wirebox\ContainerBuilder;

require_once __DIR__ . '/vendor/autoload.php';

$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();
```

### Step 3: Resolve and use services

```php
$userService = $container->get(App\UserService::class);
$userService->register('user@example.com');
```

Wirebox automatically resolves `Mailer` as a dependency of `UserService` — no manual wiring required.

---

## How Autowiring Works

When you call `$container->get(UserService::class)`, Wirebox:

1. Inspects the constructor of `UserService` via reflection
2. Sees that it requires a `Mailer` instance
3. Recursively resolves `Mailer` (creating it if needed)
4. Injects it into `UserService`'s constructor
5. Returns the fully constructed `UserService`

All of this happens automatically. No configuration files, no manual bindings.

{: .note }
By default, all services are **singletons** — the same instance is returned on every `get()` call. Use the `#[Transient]` attribute to get a new instance each time.

---

## Working with Interfaces

When your code depends on interfaces, Wirebox auto-binds them if there's exactly one implementation:

```php
// src/LoggerInterface.php
namespace App;

interface LoggerInterface
{
    public function log(string $message): void;
}

// src/FileLogger.php
namespace App;

class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        file_put_contents('/var/log/app.log', $message . "\n", FILE_APPEND);
    }
}

// src/OrderService.php
namespace App;

class OrderService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }
}
```

Since `FileLogger` is the only class implementing `LoggerInterface` in the scanned directory, Wirebox binds the interface automatically:

```php
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

$orderService = $container->get(App\OrderService::class);
// LoggerInterface is auto-bound to FileLogger
```

If there are multiple implementations, you need an explicit binding:

```php
$builder->bind(App\LoggerInterface::class, App\FileLogger::class);
```

---

## Adding Environment Variables

Create a `.env` file in your project root:

```env
DB_HOST=localhost
DB_PORT=5432
APP_DEBUG=true
```

Use the `#[Param]` attribute to inject environment variables:

```php
use AsceticSoft\Wirebox\Attribute\Param;

class DatabaseService
{
    public function __construct(
        #[Param('DB_HOST')] private string $host,
        #[Param('DB_PORT')] private int $port,
    ) {
    }
}
```

Or define parameters on the builder:

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
```

---

## A Complete Bootstrap Example

```php
<?php
// bootstrap.php

use AsceticSoft\Wirebox\ContainerBuilder;

require_once __DIR__ . '/vendor/autoload.php';

$builder = new ContainerBuilder(projectDir: __DIR__);

// Exclude non-service classes
$builder->exclude('Entity/*');
$builder->exclude('Migration/*');

// Auto-register all classes in src/
$builder->scan(__DIR__ . '/src');

// Explicit bindings for ambiguous interfaces
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->bind(CacheInterface::class, RedisCache::class);

// Parameters from environment
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');

// Custom factory for complex instantiation
$builder->register(PDO::class, function ($c) {
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=app',
            $c->getParameter('db.host'),
            $c->getParameter('db.port'),
        ),
    );
});

// Build and go
$container = $builder->build();
$app = $container->get(App\Kernel::class);
$app->run();
```

---

## What's Next?

- [Configuration]({% link docs/configuration.md %}) — Learn about directory scanning, bindings, factories, and the fluent API
- [Attributes]({% link docs/attributes.md %}) — Full reference for all PHP attributes
- [Environment Variables]({% link docs/environment.md %}) — Deep dive into dotenv support and type casting
- [Compiled Container]({% link docs/compiled-container.md %}) — Optimize for production with zero-reflection containers
- [Advanced Features]({% link docs/advanced.md %}) — Autoconfiguration, tagged services, lazy proxies, and more
