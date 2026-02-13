---
title: Home
layout: home
nav_order: 1
---

# Wirebox

{: .fs-9 }

Lightweight PHP 8.4 DI Container with Autowiring, Attributes & Compiled Container.
{: .fs-6 .fw-300 }

[![CI](https://github.com/ascetic-soft/Wirebox/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/Wirebox/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/Wirebox/graph/badge.svg)](https://codecov.io/gh/ascetic-soft/Wirebox)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/wirebox/php)](https://packagist.org/packages/ascetic-soft/wirebox)
[![License](https://img.shields.io/packagist/l/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)

[Get Started]({% link docs/getting-started.md %}){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[View on GitHub](https://github.com/ascetic-soft/Wirebox){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## What is Wirebox?

Wirebox is a modern, zero-configuration **dependency injection container** for PHP 8.4+. It leverages the latest PHP features — native attributes, lazy objects, and reflection — to provide a powerful yet simple DI experience.

### Key Highlights

- **Zero configuration** — Point at a directory, all concrete classes are auto-registered
- **PSR-11 compatible** — Standard `ContainerInterface` implementation
- **PHP 8.4 Attributes** — Configure services declaratively with `#[Singleton]`, `#[Inject]`, `#[Lazy]`, and more
- **Autowiring** — Constructor dependencies resolved automatically via type hints
- **Compiled container** — Generate a PHP class with zero reflection at runtime for production
- **Lazy proxies** — Deferred instantiation via PHP 8.4 native lazy objects
- **Built-in dotenv** — No external dependencies for environment variable support
- **Autoconfiguration** — Symfony-style auto-tagging by interface or attribute

---

## Quick Example

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

$service = $container->get(App\UserService::class);
```

That's it. Three lines to get a fully working DI container with autowiring. No XML, no YAML, no boilerplate.

---

## Why Wirebox?

| Feature | Wirebox | Other containers |
|:--------|:--------|:-----------------|
| PHP 8.4 native lazy objects | Yes | Proxy generation |
| Zero-config directory scanning | Yes | Manual registration |
| Built-in `.env` support | Yes | External packages |
| Compiled container | Yes | Some |
| Autoconfiguration | Yes | Some |
| Minimal dependencies | `psr/container` only | Often many |
| PHPStan Level 9 | Yes | Varies |

---

## Requirements

- **PHP** >= 8.4
- **psr/container** ^2.0

## Installation

```bash
composer require ascetic-soft/wirebox
```

---

## Documentation

<div class="grid-container">
  <div class="grid-item">
    <h3><a href="{% link docs/getting-started.md %}">Getting Started</a></h3>
    <p>Installation, first container, and basic usage in 5 minutes.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{% link docs/configuration.md %}">Configuration</a></h3>
    <p>Directory scanning, bindings, factories, and fluent API.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{% link docs/attributes.md %}">Attributes</a></h3>
    <p>All PHP attributes: Singleton, Transient, Inject, Param, Tag, and more.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{% link docs/environment.md %}">Environment Variables</a></h3>
    <p>Built-in dotenv, 3-level priority, type casting.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{% link docs/compiled-container.md %}">Compiled Container</a></h3>
    <p>Zero-reflection production container generation.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{% link docs/advanced.md %}">Advanced</a></h3>
    <p>Autoconfiguration, tagged services, lazy proxies, circular dependencies.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{% link docs/api-reference.md %}">API Reference</a></h3>
    <p>Complete reference for all public classes and methods.</p>
  </div>
</div>
