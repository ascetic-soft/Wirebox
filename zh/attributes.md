---
title: 属性
layout: default
nav_order: 4
parent: 中文
---

# PHP 属性
{: .no_toc }

使用 PHP 8.4 原生属性进行声明式服务配置。
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>目录</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## 概览

Wirebox 提供一组 PHP 属性，直接在类定义中声明式配置服务，无需外部配置文件。

| 属性 | 目标 | 说明 |
|:-----|:-----|:-----|
| `#[Singleton]` | 类 | 每个容器一个实例（默认） |
| `#[Transient]` | 类 | 每次 `get()` 新实例 |
| `#[Lazy]` | 类 | 通过代理延迟实例化 |
| `#[Eager]` | 类 | 禁用默认 lazy 模式 |
| `#[Tag]` | 类 | 分组检索标签（可重复） |
| `#[Inject]` | 参数 | 覆盖类型提示的服务 |
| `#[Param]` | 参数 | 注入环境变量 |
| `#[Exclude]` | 类 | 扫描时跳过 |
| `#[AutoconfigureTag]` | 类 | 按接口或属性自动标记 |

所有属性在 `AsceticSoft\Wirebox\Attribute` 命名空间下。

---

## #[Singleton]

将类标记为单例。由于这是默认行为，主要用于明确表达意图：

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

**等效 Fluent API：**

```php
$builder->register(DatabaseConnection::class)->singleton();
```

---

## #[Transient]

每次 `get()` 调用创建新实例：

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

每次调用 `$container->get(RequestContext::class)` 返回新实例。

**等效 Fluent API：**

```php
$builder->register(RequestContext::class)->transient();
```

{: .tip }
对持有请求特定状态的服务使用 `#[Transient]`，如请求上下文、表单数据或 DTO。

---

## #[Lazy]

立即返回轻量级代理；只有在首次访问属性或方法时才创建真实实例。使用 PHP 8.4 原生惰性对象 (`ReflectionClass::newLazyProxy`)：

```php
use AsceticSoft\Wirebox\Attribute\Lazy;

#[Lazy]
class HeavyReportGenerator
{
    public function __construct(
        private PDO $db,
        private CacheInterface $cache,
    ) {
        // 昂贵的初始化 —— 只在实际需要时执行
    }
}
```

**关键特性：**
- 代理是类的真实实例（通过 `instanceof` 检查）
- 创建延迟到首次属性或方法访问
- 编译容器完全支持
- 可与 `#[Transient]` 组合，每次 `get()` 获取新的惰性代理

**等效 Fluent API：**

```php
$builder->register(HeavyReportGenerator::class)->lazy();
```

{: .note }
当 `defaultLazy` 启用时（默认），**所有**服务都是惰性的，除非标记了 `#[Eager]`。`#[Lazy]` 属性仅在 `defaultLazy` 关闭时需要。

---

## #[Eager]

当容器默认 lazy 模式启用时，禁用惰性实例化：

```php
use AsceticSoft\Wirebox\Attribute\Eager;

#[Eager]
class AppConfig
{
    public function __construct()
    {
        // 即使 defaultLazy 开启，也始终立即创建
    }
}
```

适用于：
- 必须尽早初始化的服务（配置、事件订阅者）
- 构造函数有副作用的服务
- 需要在启动时验证设置的服务

**等效 Fluent API：**

```php
$builder->register(AppConfig::class)->eager();
```

---

## #[Tag]

为类添加标签以进行分组检索。属性**可重复** —— 一个类可以有多个标签：

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

检索特定标签的所有服务：

```php
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}
```

**等效 Fluent API：**

```php
$builder->register(UserCreatedListener::class)->tag('event.listener', 'audit');
```

{: .tip }
标签非常适合插件系统、事件分发器、中间件链和命令/查询总线模式。

---

## #[AutoconfigureTag]

自动标记实现某接口或被某属性装饰的所有类。放在**接口**或**属性**类上。

### 在接口上

所有实现类自动获得标签：

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

// 扫描时自动标记为 'command.handler'
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void
    {
        // ...
    }
}
```

### 在自定义属性上

所有被该属性装饰的类获得标签：

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
#[AutoconfigureTag('scheduler.task')]
class AsScheduled {}

// 扫描时自动标记为 'scheduler.task'
#[AsScheduled]
class DailyReportTask
{
    public function run(): void { /* ... */ }
}
```

### 多个标签

属性可重复：

```php
#[AutoconfigureTag('command.handler')]
#[AutoconfigureTag('auditable')]
interface CommandHandlerInterface {}
```

{: .note }
带有 `#[AutoconfigureTag]` 的接口不受歧义自动绑定检查影响 —— 多个实现是预期行为。

另见[自动配置]({{ '/zh/advanced.html' | relative_url }}#自动配置)了解编程式配置。

---

## #[Inject]

覆盖特定构造函数参数的类型提示服务：

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

没有 `#[Inject]`，Wirebox 会通过容器绑定解析 `MailerInterface`。有了 `#[Inject]`，无论绑定如何都始终注入 `SmtpMailer`。

**使用场景：**
- 某个服务需要特定实现，其他地方需要另一个
- 接口没有全局绑定
- 需要为特定消费者覆盖全局绑定

---

## #[Param]

直接将环境变量的标量值注入构造函数参数：

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

**类型转换**根据参数类型提示自动进行：

| PHP 类型 | 环境值 | 结果 |
|:---------|:-------|:-----|
| `string` | `"localhost"` | `"localhost"` |
| `int` | `"5432"` | `5432` |
| `float` | `"1.5"` | `1.5` |
| `bool` | `"true"` / `"1"` | `true` |
| `bool` | `"false"` / `"0"` / `""` | `false` |

{: .tip }
`#[Param]` 直接从环境变量读取（使用三级优先级系统）。这是注入配置值的最简方式。

详见[环境变量]({{ '/zh/environment.html' | relative_url }})。

---

## #[Exclude]

目录扫描时排除类的自动注册：

```php
use AsceticSoft\Wirebox\Attribute\Exclude;

#[Exclude]
class InternalHelper
{
    // 不会注册到容器中
}
```

**使用场景：**
- 不应成为服务的辅助/工具类
- 不用于直接实例化的基类
- 意外出现在扫描目录中的测试替身

{: .note }
`#[Exclude]` 仅影响目录扫描。仍可通过 `$builder->register()` 手动注册被排除的类。

---

## 组合属性

属性可自由组合：

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

## 属性 vs Fluent API

每个属性都有等效的 fluent API 调用，选择你喜欢的风格：

| 属性 | Fluent API |
|:-----|:-----------|
| `#[Singleton]` | `->singleton()` |
| `#[Transient]` | `->transient()` |
| `#[Lazy]` | `->lazy()` |
| `#[Eager]` | `->eager()` |
| `#[Tag('x')]` | `->tag('x')` |
| `#[Exclude]` | `$builder->exclude(...)` |

{: .tip }
**属性**适合与类定义一起的配置。**Fluent API** 适合外部或条件配置（如不同环境使用不同绑定）。
