---
title: 高级特性
layout: default
nav_order: 7
parent: 中文
---

# 高级特性
{: .no_toc }

自动配置、标签服务、惰性代理、循环依赖与错误处理。
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>目录</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## 自动配置

自动配置可根据服务实现的接口或携带的属性，自动为其添加标签和配置——类似 Symfony 的自动配置。

### 声明式：`#[AutoconfigureTag]`

在**接口**或**自定义属性**上放置 `#[AutoconfigureTag]`，自动标记所有实现/装饰的类。

#### 在接口上

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

// 自动标记为 'command.handler'
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

class DeleteUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}
```

#### 在自定义属性上

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
#[AutoconfigureTag('scheduler.task')]
class AsScheduled {}

// 自动标记为 'scheduler.task'
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

### 编程式：`registerForAutoconfiguration()`

需要更多控制时（生命周期、lazy 模式、多标签）：

```php
$builder->registerForAutoconfiguration(EventListenerInterface::class)
    ->tag('event.listener')
    ->singleton()
    ->lazy();
```

`scan()` 过程中找到的任何实现 `EventListenerInterface` 的类都会自动：
- 获得 `event.listener` 标签
- 配置为单例
- 使用惰性代理

也适用于自定义属性：

```php
$builder->registerForAutoconfiguration(AsScheduled::class)
    ->tag('scheduler.task')
    ->transient();
```

### CQRS 示例

使用自动配置的完整命令/查询总线设置：

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

// 定义带自动标记的处理器接口
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

// 命令处理器——自动标记
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

class DeleteUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

// 查询处理器——自动标记
class GetUserHandler implements QueryHandlerInterface
{
    public function __invoke(object $query): mixed { /* ... */ }
}
```

构建并使用：

```php
$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

// 所有命令处理器
foreach ($container->getTagged('command.handler') as $handler) {
    // CreateUserHandler, DeleteUserHandler
}

// 所有查询处理器
foreach ($container->getTagged('query.handler') as $handler) {
    // GetUserHandler
}
```

{: .note }
自动配置的接口不受歧义自动绑定检查影响。`CommandHandlerInterface` 的多个实现不会报错——它们应通过标签检索。

---

## 标签服务

标签将服务分组以便集体检索。对事件分发、中间件链和插件系统等模式至关重要。

### 添加标签

通过属性：

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

通过 fluent API：

```php
$builder->register(OrderCreatedListener::class)->tag('event.listener');
$builder->register(AuditListener::class)->tag('event.listener', 'audit');
```

### 检索标签服务

```php
// 所有事件监听器
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}

// 所有审计服务
foreach ($container->getTagged('audit') as $service) {
    // ...
}
```

### 事件分发器示例

```php
class EventDispatcher
{
    /** @var iterable<EventListenerInterface> */
    private iterable $listeners;

    public function __construct(ContainerInterface $container)
    {
        // 惰性——监听器在迭代前不会实例化
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

## 惰性代理

惰性代理将服务构造延迟到实际使用时。基于 PHP 8.4 原生 `ReflectionClass::newLazyProxy()`。

### 工作原理

1. 请求惰性服务时，立即返回**代理对象**
2. 代理是类的真实实例（通过 `instanceof` 检查）
3. 访问任何属性或调用方法时，构造**真实实例**
4. 后续访问使用已构造的实例

### 默认 Lazy 模式

默认情况下，`ContainerBuilder` 启用 lazy 模式。所有服务都是惰性的，除非：
- 显式标记 `#[Eager]`
- 通过 fluent API 的 `->eager()` 配置

```php
// 此容器中所有服务默认为惰性
$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();
```

禁用：

```php
$builder->defaultLazy(false);
// 现在只有带 #[Lazy] 的服务才是惰性的
```

### 性能优势

惰性代理在以下情况特别有价值：
- 服务有**昂贵的构造函数**（数据库连接、HTTP 客户端）
- **服务众多**但每个请求只用到少数
- 服务有**复杂的依赖链**且并非总是需要

```php
#[Lazy]
class ElasticsearchClient
{
    public function __construct()
    {
        // 昂贵：建立连接、检查集群健康
        $this->client = ClientBuilder::create()
            ->setHosts(['elasticsearch:9200'])
            ->build();
    }
}

// 代理立即返回——未建立连接
$client = $container->get(ElasticsearchClient::class);

// 此时才建立连接
$client->search(['index' => 'products', 'body' => [...]]);
```

---

## 循环依赖

Wirebox 在**构建时**检测循环依赖——在容器使用之前。

### 安全的循环

循环依赖**只有**当循环中**所有**服务都是**惰性单例**时才安全：

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

$container = $builder->build(); // OK——两者都是惰性单例

$a = $container->get(ServiceA::class);
assert($a->b->a === $a); // 同一个代理实例
```

**原理：** 代理在真实实例化之前缓存到单例存储中。当依赖链回环时，它找到缓存中的代理，而非重新进入构造。

### 不安全的循环

| 场景 | 结果 |
|:-----|:-----|
| 所有服务都是惰性单例 | **安全** — 代理在实例化前缓存 |
| 任何服务是 **eager** | **不安全** — Autowirer 两次访问同一类 |
| 任何服务是**惰性 transient** | **不安全** — 代理未缓存，无限递归 |

不安全的循环会给出清晰的错误消息：

```
Circular dependency detected: ServiceA -> ServiceB -> ServiceA.
All services in a circular dependency must be lazy singletons.
Unsafe: ServiceB (not lazy)
```

{: .tip }
遇到循环依赖错误时，解决方案通常是：
1. 让循环中所有服务成为惰性单例（最简单）
2. 重构以打破循环（提取共享依赖）
3. 使用 setter 注入延迟其中一个依赖

{: .note }
工厂定义（`register(..., fn() => ...)`）在循环分析中被跳过，因为无法静态确定其依赖。

---

## Setter 注入

配置服务创建后要调用的方法：

```php
$builder->register(Mailer::class)
    ->call('setTransport', [SmtpTransport::class])
    ->call('setLogger', [FileLogger::class]);
```

容器从容器中解析类名参数，标量参数原样传递。多次 `call()` 按顺序执行。

### 使用场景

- 不应放在构造函数中的**可选依赖**
- 服务在创建后配置的**框架集成**
- 通过延迟一条边来**打破循环依赖**

---

## 自注册

容器将自身注册为 `Psr\Container\ContainerInterface`。可在任何服务中通过类型提示获取：

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
注入容器本身是**服务定位器**反模式。优先使用具体依赖的构造函数注入。仅在确实需要动态服务解析时使用自注册（如插件加载器、命令总线）。

---

## 错误处理

Wirebox 抛出特定的、描述性的异常：

| 异常 | 触发条件 |
|:-----|:---------|
| `NotFoundException` | 服务未找到且无法自动装配 |
| `AutowireException` | 无法解析构造函数参数（无类型提示、不可解析的类型） |
| `CircularDependencyException` | 构建时检测到不安全的循环依赖 |
| `ContainerException` | 通用容器错误（歧义绑定、无效配置） |

所有异常实现 `Psr\Container\ContainerExceptionInterface`。

### 示例：捕获错误

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
    // 服务未找到
}
```

{: .tip }
所有异常包含详细消息和完整依赖路径，便于诊断问题。
