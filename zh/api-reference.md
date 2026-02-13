---
title: API 参考
layout: default
nav_order: 8
parent: 中文
---

# API 参考
{: .no_toc }

所有公共类和方法的完整参考。
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>目录</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## ContainerBuilder

配置和构建 DI 容器的主入口。

```php
use AsceticSoft\Wirebox\ContainerBuilder;
```

### 构造函数

```php
new ContainerBuilder(string $projectDir)
```

| 参数 | 类型 | 说明 |
|:-----|:-----|:-----|
| `$projectDir` | `string` | 解析 `.env` 文件的基础目录 |

### 方法

#### `scan(string $directory): void`

递归扫描目录并自动注册所有具体类。跳过抽象类、接口、Trait 和枚举。带 `#[Exclude]` 的类也会跳过。

```php
$builder->scan(__DIR__ . '/src');
```

如果接口恰好有一个实现，自动绑定。多个实现会导致歧义绑定错误（除非用 `bind()` 解决）。

---

#### `exclude(string $pattern): void`

按 glob 模式从后续扫描中排除文件。模式相对于扫描目录。

```php
$builder->exclude('Entity/*');
$builder->exclude('*Test.php');
```

{: .important }
必须在 `scan()` **之前**调用。

---

#### `bind(string $abstract, string $concrete): void`

将接口或抽象类绑定到具体实现。

```php
$builder->bind(LoggerInterface::class, FileLogger::class);
```

| 参数 | 类型 | 说明 |
|:-----|:-----|:-----|
| `$abstract` | `string` | 接口或抽象类的 FQCN |
| `$concrete` | `string` | 具体类的 FQCN |

---

#### `register(string $id, ?Closure $factory = null): Definition`

按 ID 注册服务，可选工厂闭包。返回 `Definition` 用于 fluent 配置。

```php
// 带工厂
$builder->register(PDO::class, fn($c) => new PDO(...));

// 不带工厂（用于 fluent 配置）
$builder->register(Mailer::class)
    ->transient()
    ->tag('mail');
```

| 参数 | 类型 | 说明 |
|:-----|:-----|:-----|
| `$id` | `string` | 服务标识符（通常是 FQCN） |
| `$factory` | `?Closure` | 可选工厂闭包，接收 `Container` |

**返回：** `Definition`

---

#### `parameter(string $name, mixed $value): void`

定义命名参数。值可包含 `%env(...)%` 表达式。

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.name', 'My App');
```

| 参数 | 类型 | 说明 |
|:-----|:-----|:-----|
| `$name` | `string` | 参数名称 |
| `$value` | `mixed` | 值（普通值或含 env 表达式） |

---

#### `defaultLazy(bool $lazy): void`

设置默认 lazy 模式。启用时（默认），所有服务为惰性代理，除非标记 `#[Eager]`。

```php
$builder->defaultLazy(false); // 禁用默认 lazy
```

---

#### `registerForAutoconfiguration(string $classOrInterface): AutoconfigureRule`

为接口或属性注册自动配置规则。返回 `AutoconfigureRule` 用于 fluent 配置。

```php
$builder->registerForAutoconfiguration(EventListenerInterface::class)
    ->tag('event.listener')
    ->singleton()
    ->lazy();
```

| 参数 | 类型 | 说明 |
|:-----|:-----|:-----|
| `$classOrInterface` | `string` | 接口或属性的 FQCN |

**返回：** `AutoconfigureRule`

---

#### `build(): Container`

构建并返回运行时容器。验证配置、检测循环依赖、应用默认 lazy 模式。

```php
$container = $builder->build();
```

**返回：** `Container`

**抛出：** `ContainerException`、`CircularDependencyException`

---

#### `compile(string $outputPath, string $className, string $namespace): void`

生成编译容器 PHP 类。执行与 `build()` 相同的验证。

```php
$builder->compile(
    outputPath: __DIR__ . '/var/cache/CompiledContainer.php',
    className: 'CompiledContainer',
    namespace: 'App\Cache',
);
```

| 参数 | 类型 | 说明 |
|:-----|:-----|:-----|
| `$outputPath` | `string` | 生成文件路径 |
| `$className` | `string` | 生成的类名 |
| `$namespace` | `string` | 生成类的命名空间 |

**抛出：** `ContainerException`、`CircularDependencyException`

---

## Container

运行时依赖注入容器。实现 `Psr\Container\ContainerInterface`。

```php
use AsceticSoft\Wirebox\Container;
```

### 方法

#### `get(string $id): mixed`

按 ID 解析并返回服务。单例在首次创建后缓存。

```php
$service = $container->get(UserService::class);
```

**抛出：** `NotFoundException`、`AutowireException`、`CircularDependencyException`

---

#### `has(string $id): bool`

检查服务是否可解析。

```php
if ($container->has(UserService::class)) {
    // ...
}
```

---

#### `getTagged(string $tag): iterable`

返回给定标签的所有服务的迭代器。服务在迭代时惰性解析。

```php
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}
```

| 参数 | 类型 | 说明 |
|:-----|:-----|:-----|
| `$tag` | `string` | 标签名称 |

**返回：** `iterable<object>`

---

#### `getParameter(string $name): mixed`

获取参数值。Env 表达式在首次访问时解析。

```php
$host = $container->getParameter('db.host');
```

**抛出：** `ContainerException`（参数未找到时）

---

#### `getParameters(): array`

返回所有参数的关联数组。

```php
$params = $container->getParameters();
// ['db.host' => 'localhost', 'db.port' => 5432, ...]
```

---

## Definition

服务定义的 fluent 构建器。由 `ContainerBuilder::register()` 返回。

```php
use AsceticSoft\Wirebox\Definition;
```

### 方法

| 方法 | 说明 |
|:-----|:-----|
| `singleton(): self` | 配置为单例（每个容器一个实例，默认） |
| `transient(): self` | 配置为 transient（每次 `get()` 新实例） |
| `lazy(): self` | 启用惰性代理 |
| `eager(): self` | 禁用惰性代理 |
| `tag(string ...$tags): self` | 添加一个或多个标签 |
| `call(string $method, array $arguments = []): self` | 配置构造后方法调用（setter 注入） |

---

## AutoconfigureRule

自动配置规则的 fluent 构建器。由 `ContainerBuilder::registerForAutoconfiguration()` 返回。

```php
use AsceticSoft\Wirebox\AutoconfigureRule;
```

### 方法

| 方法 | 说明 |
|:-----|:-----|
| `tag(string ...$tags): self` | 为匹配的服务添加标签 |
| `singleton(): self` | 将匹配的服务设为单例 |
| `transient(): self` | 将匹配的服务设为 transient |
| `lazy(): self` | 为匹配的服务启用 lazy 模式 |
| `eager(): self` | 为匹配的服务禁用 lazy 模式 |

---

## Lifetime

服务生命周期枚举。

```php
use AsceticSoft\Wirebox\Lifetime;
```

| 值 | 说明 |
|:---|:-----|
| `Lifetime::Singleton` | 每个容器一个实例 |
| `Lifetime::Transient` | 每次新实例 |

---

## 异常

所有异常在 `AsceticSoft\Wirebox\Exception` 命名空间下，实现 `Psr\Container\ContainerExceptionInterface`。

### NotFoundException

服务未找到或无法自动装配时抛出。实现 `Psr\Container\NotFoundExceptionInterface`。

### AutowireException

构造函数参数无法解析时抛出（无类型提示、不可解析类型、缺少必需参数）。

### CircularDependencyException

检测到不安全循环依赖时抛出。消息包含：
- 完整循环路径（如 `ServiceA -> ServiceB -> ServiceA`）
- 哪些服务不安全及原因

### ContainerException

通用容器错误。常见原因：
- 歧义自动绑定（多个实现，无显式 `bind()`）
- 无效配置
- 参数未找到

---

## 属性汇总

所有属性在 `AsceticSoft\Wirebox\Attribute` 命名空间下。

| 属性 | 目标 | 可重复 | 说明 |
|:-----|:-----|:-------|:-----|
| `#[Singleton]` | 类 | 否 | 单例生命周期 |
| `#[Transient]` | 类 | 否 | Transient 生命周期 |
| `#[Lazy]` | 类 | 否 | 惰性代理 |
| `#[Eager]` | 类 | 否 | 禁用 lazy |
| `#[Tag('name')]` | 类 | 是 | 添加标签 |
| `#[Inject(Class::class)]` | 参数 | 否 | 覆盖类型提示 |
| `#[Param('ENV_VAR')]` | 参数 | 否 | 注入环境变量 |
| `#[Exclude]` | 类 | 否 | 扫描时跳过 |
| `#[AutoconfigureTag('tag')]` | 类 | 是 | 自动标记实现 |
