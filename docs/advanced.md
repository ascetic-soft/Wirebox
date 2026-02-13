---
title: Advanced Features
layout: default
nav_order: 7
---

# Advanced Features
{: .no_toc }

Autoconfiguration, tagged services, lazy proxies, circular dependencies, and error handling.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Autoconfiguration

Autoconfiguration lets you automatically tag and configure services based on the interfaces they implement or the attributes they carry — similar to Symfony's autoconfiguration.

### Declarative: `#[AutoconfigureTag]`

Place the `#[AutoconfigureTag]` attribute on an **interface** or **custom attribute** to auto-tag all implementing/decorated classes.

#### On an Interface

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

// Automatically tagged as 'command.handler'
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

class DeleteUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}
```

#### On a Custom Attribute

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
#[AutoconfigureTag('scheduler.task')]
class AsScheduled {}

// Automatically tagged as 'scheduler.task'
#[AsScheduled]
class DailyReportTask
{
    public function run(): void { /* ... */ }
}

#[AsScheduled]
class CleanupTask
{
    public function run(): void { /* ... */ }
}
```

### Programmatic: `registerForAutoconfiguration()`

For more control over configuration (lifetime, lazy mode, multiple tags):

```php
$builder->registerForAutoconfiguration(EventListenerInterface::class)
    ->tag('event.listener')
    ->singleton()
    ->lazy();
```

Any class implementing `EventListenerInterface` found during `scan()` will automatically:
- Get the `event.listener` tag
- Be configured as a singleton
- Use lazy proxies

Works with custom attributes too:

```php
$builder->registerForAutoconfiguration(AsScheduled::class)
    ->tag('scheduler.task')
    ->transient();
```

### CQRS Example

A complete command/query bus setup using autoconfiguration:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

// Define handler interfaces with auto-tagging
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

// Command handlers — auto-tagged
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

class DeleteUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

// Query handlers — auto-tagged
class GetUserHandler implements QueryHandlerInterface
{
    public function __invoke(object $query): mixed { /* ... */ }
}
```

Build and use:

```php
$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

// All command handlers
foreach ($container->getTagged('command.handler') as $handler) {
    // CreateUserHandler, DeleteUserHandler
}

// All query handlers
foreach ($container->getTagged('query.handler') as $handler) {
    // GetUserHandler
}
```

{: .note }
Autoconfigured interfaces are excluded from ambiguous auto-binding checks. Multiple implementations of `CommandHandlerInterface` won't cause an error — they're expected to be retrieved via tags.

---

## Tagged Services

Tags let you group services for collective retrieval. They're essential for patterns like event dispatching, middleware chains, and plugin systems.

### Adding Tags

Via attribute:

```php
use AsceticSoft\Wirebox\Attribute\Tag;

#[Tag('event.listener')]
class OrderCreatedListener { /* ... */ }

#[Tag('event.listener')]
class UserCreatedListener { /* ... */ }

#[Tag('event.listener')]
#[Tag('audit')]
class AuditListener { /* ... */ }
```

Via fluent API:

```php
$builder->register(OrderCreatedListener::class)->tag('event.listener');
$builder->register(AuditListener::class)->tag('event.listener', 'audit');
```

### Retrieving Tagged Services

```php
// Get all event listeners
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}

// Get all auditable services
foreach ($container->getTagged('audit') as $service) {
    // ...
}
```

### Event Dispatcher Example

```php
class EventDispatcher
{
    /** @var iterable<EventListenerInterface> */
    private iterable $listeners;

    public function __construct(ContainerInterface $container)
    {
        // Lazy — listeners are not instantiated until iterated
        $this->listeners = $container->getTagged('event.listener');
    }

    public function dispatch(object $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->handle($event);
        }
    }
}
```

---

## Lazy Proxies

Lazy proxies defer service construction until the service is actually used. This is powered by PHP 8.4's native `ReflectionClass::newLazyProxy()`.

### How It Works

1. When you request a lazy service, a **proxy object** is returned immediately
2. The proxy is a real instance of the class (passes `instanceof` checks)
3. When you access any property or call any method, the **real instance** is constructed
4. Subsequent accesses use the already-constructed instance

### Default Lazy Mode

By default, `ContainerBuilder` has lazy mode **enabled**. This means all services are lazy unless:
- Explicitly marked with `#[Eager]`
- Configured via `->eager()` on the fluent API

```php
// All services in this container are lazy by default
$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();
```

To disable:

```php
$builder->defaultLazy(false);
// Now only services with #[Lazy] are lazy
```

### Performance Benefits

Lazy proxies are especially valuable when:
- Services have **expensive constructors** (database connections, HTTP clients)
- You have **many services** but only use a few per request
- Services have **complex dependency chains** that aren't always needed

```php
#[Lazy]
class ElasticsearchClient
{
    public function __construct()
    {
        // Expensive: establishes connection, checks cluster health
        $this->client = ClientBuilder::create()
            ->setHosts(['elasticsearch:9200'])
            ->build();
    }
}

// Proxy returned instantly — no connection made
$client = $container->get(ElasticsearchClient::class);

// Connection established only now
$client->search(['index' => 'products', 'body' => [...]]);
```

---

## Circular Dependencies

Wirebox detects circular dependencies **at build time** — before the container is ever used.

### Safe Cycles

A circular dependency is safe **only** when **all** services in the cycle are **lazy singletons**:

```php
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

$container = $builder->build(); // OK — both are lazy singletons

$a = $container->get(ServiceA::class);
assert($a->b->a === $a); // Same proxy instance
```

**Why it works:** The proxy is cached in the singleton store before real instantiation begins. When the dependency chain loops back, it finds the proxy in the cache instead of re-entering construction.

### Unsafe Cycles

| Scenario | Result |
|:---------|:-------|
| All services are lazy singletons | **Safe** — proxy cached before instantiation |
| Any service is **eager** | **Unsafe** — Autowirer hits the same class twice |
| Any service is **lazy transient** | **Unsafe** — proxy not cached, infinite recursion |

Unsafe cycles produce clear error messages:

```
Circular dependency detected: ServiceA -> ServiceB -> ServiceA.
All services in a circular dependency must be lazy singletons.
Unsafe: ServiceB (not lazy)
```

{: .tip }
If you encounter a circular dependency error, the fix is usually one of:
1. Make all services in the cycle lazy singletons (the simplest fix)
2. Refactor to break the cycle (extract a shared dependency)
3. Use setter injection to defer one dependency

{: .note }
Factory-based definitions (`register(..., fn() => ...)`) are skipped during cycle analysis because their dependencies cannot be determined statically.

---

## Setter Injection

Configure methods to be called after a service is constructed:

```php
$builder->register(Mailer::class)
    ->call('setTransport', [SmtpTransport::class])
    ->call('setLogger', [FileLogger::class]);
```

The container resolves class-name arguments from the container and passes scalar arguments as-is. Multiple `call()` invocations are executed in order.

### Use Cases

- **Optional dependencies** that shouldn't be in the constructor
- **Framework integration** where services are configured after creation
- **Breaking circular dependencies** by deferring one edge

---

## Self-Resolution

The container registers itself as `Psr\Container\ContainerInterface`. You can type-hint it in any service:

```php
use Psr\Container\ContainerInterface;

class ServiceLocator
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function getService(string $id): object
    {
        return $this->container->get($id);
    }
}
```

{: .warning }
Injecting the container itself is a **Service Locator** anti-pattern. Prefer constructor injection of specific dependencies. Use self-resolution only when you genuinely need dynamic service resolution (e.g., plugin loaders, command buses).

---

## Error Handling

Wirebox throws specific, descriptive exceptions:

| Exception | When |
|:----------|:-----|
| `NotFoundException` | Service not found and cannot be autowired |
| `AutowireException` | Cannot resolve a constructor parameter (no type hint, unresolvable type) |
| `CircularDependencyException` | Unsafe circular dependency detected at build time |
| `ContainerException` | General errors (ambiguous bindings, invalid configuration) |

All exceptions implement `Psr\Container\ContainerExceptionInterface`.

### Example: Catching Errors

```php
use AsceticSoft\Wirebox\Exception\CircularDependencyException;
use AsceticSoft\Wirebox\Exception\ContainerException;
use Psr\Container\NotFoundExceptionInterface;

try {
    $container = $builder->build();
} catch (CircularDependencyException $e) {
    // "Circular dependency detected: A -> B -> A. ..."
    error_log($e->getMessage());
} catch (ContainerException $e) {
    // "Ambiguous auto-binding for PaymentInterface: StripePayment, PayPalPayment"
    error_log($e->getMessage());
}

try {
    $service = $container->get('NonExistentService');
} catch (NotFoundExceptionInterface $e) {
    // Service not found
}
```

{: .tip }
All exceptions include detailed messages with the full dependency path, making it easy to diagnose issues.
