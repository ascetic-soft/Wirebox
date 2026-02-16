---
title: 配置
layout: default
nav_order: 3
parent: 中文
---

# 配置
{: .no_toc }

配置 Wirebox 容器所需的一切。
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>目录</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## ContainerBuilder

`ContainerBuilder` 是配置容器的主入口：

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);
```

`projectDir` 参数用作解析 `.env` 文件的基础路径。

---

## 目录扫描

扫描一个或多个目录以自动注册所有具体类。抽象类、接口、Trait 和枚举会自动跳过：

```php
$builder->scan(__DIR__ . '/src');
$builder->scan(__DIR__ . '/modules');
```

扫描器使用 PHP 分词器快速可靠地发现类，无需加载任何文件。

### 排除文件

通过 glob 模式排除文件。模式相对于扫描目录：

```php
$builder->exclude('Entity/*');
$builder->exclude('*Test.php');
$builder->scan(__DIR__ . '/src');
```

{: .important }
必须在 `scan()` **之前**调用 `exclude()` —— 排除模式应用于后续的扫描。

也可以用 `#[Exclude]` 属性排除单个类：

```php
use AsceticSoft\Wirebox\Attribute\Exclude;

#[Exclude]
class InternalHelper
{
    // 不会注册到容器中
}
```

### 接口自动绑定

扫描时，如果一个接口在扫描目录中**恰好有一个**实现，Wirebox 会自动将接口绑定到该实现。

如果找到两个或更多实现，绑定变为**歧义**，`build()` 将抛出 `ContainerException`。通过显式 `bind()` 解决歧义：

```php
$builder->scan(__DIR__ . '/Services');
// PaymentInterface 有 StripePayment 和 PayPalPayment —— 歧义！
$builder->bind(PaymentInterface::class, StripePayment::class);
```

{: .note }
带有 `#[AutoconfigureTag]` 属性的接口不受歧义绑定检查影响，因为多个实现是预期行为。参见[自动配置]({{ '/zh/advanced.html' | relative_url }}#自动配置)。

### 从自动绑定中排除接口

如果不需要特定绑定，但希望抑制歧义错误（例如接口在运行时解析，或仅通过标签迭代使用），可使用 `excludeFromAutoBinding()`：

```php
$builder->excludeFromAutoBinding(PaymentInterface::class);
$builder->scan(__DIR__ . '/Services');
// 无错误 — PaymentInterface 已从自动绑定检查中排除
$container = $builder->build();
```

可以一次排除多个接口：

```php
$builder->excludeFromAutoBinding(
    PaymentInterface::class,
    NotificationChannelInterface::class,
);
```

{: .note }
与 `registerForAutoconfiguration()` 不同，此方法不应用任何自动配置规则（标签、生命周期等）——仅抑制歧义错误。

---

## 接口绑定

显式将接口（或抽象类）绑定到具体实现：

```php
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->bind(CacheInterface::class, RedisCache::class);
```

解析类型提示依赖时，容器先检查绑定，再回退到具体类解析。

---

## 工厂注册

使用工厂闭包注册服务，适用于复杂的实例化逻辑：

```php
use AsceticSoft\Wirebox\Container;

$builder->register(PDO::class, function (Container $c) {
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=app',
            $c->getParameter('db.host'),
            $c->getParameter('db.port'),
        ),
    );
});
```

闭包接收 `Container` 实例，可用于解析其他服务和参数。

{: .warning }
工厂闭包**不支持**编译容器——它们需要运行时执行。尽量使用自动装配配合 `#[Param]` 或 `#[Inject]` 属性。

---

## Fluent Definition API

`register()` 返回一个 `Definition` 对象，提供 fluent 接口进行精细控制：

```php
$builder->register(FileLogger::class)
    ->transient()                                   // 每次新实例
    ->lazy()                                        // 延迟实例化
    ->tag('logger')                                 // 添加标签
    ->call('setFormatter', [JsonFormatter::class]);  // Setter 注入
```

### 可用方法

| 方法 | 说明 |
|:-----|:-----|
| `singleton()` | 每个容器一个实例（默认） |
| `transient()` | 每次 `get()` 创建新实例 |
| `lazy()` | 返回代理；首次访问时创建真实实例 |
| `eager()` | 始终立即创建（禁用默认 lazy） |
| `tag(string ...$tags)` | 添加一个或多个标签用于分组检索 |
| `call(string $method, array $args)` | 配置 setter 注入（构造后调用） |

### Setter 注入

配置服务构造后要调用的方法：

```php
$builder->register(Mailer::class)
    ->call('setTransport', [SmtpTransport::class])
    ->call('setLogger', [FileLogger::class]);
```

参数可以是：
- **类名** — 从容器解析
- **标量值** — 原样传递

---

## 参数

定义可引用环境变量的参数：

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');
$builder->parameter('rate.limit', '%env(float:RATE_LIMIT)%');
```

### 类型转换

`%env(...)%` 表达式中支持的类型转换：

| 类型 | 示例 | 结果 |
|:-----|:-----|:-----|
| `string`（默认） | `%env(DB_HOST)%` | `"localhost"` |
| `int` | `%env(int:DB_PORT)%` | `5432` |
| `float` | `%env(float:RATE_LIMIT)%` | `1.5` |
| `bool` | `%env(bool:APP_DEBUG)%` | `true` |

### 嵌入表达式

环境表达式可嵌入到字符串中：

```php
$builder->parameter('dsn', 'mysql:host=%env(DB_HOST)%;port=%env(DB_PORT)%');
// 结果: "mysql:host=localhost;port=5432"
```

### 普通值

参数也可以是不含 env 表达式的普通值：

```php
$builder->parameter('pagination.limit', 25);
$builder->parameter('app.name', 'My Application');
```

---

## 默认 Lazy 模式

默认情况下，`ContainerBuilder` 启用 **lazy 模式** —— 所有服务都创建为惰性代理，除非带有显式的 `#[Eager]` 属性。这通常是性能最佳选择。

禁用默认 lazy 模式：

```php
$builder->defaultLazy(false);
```

禁用后，除非显式标记 `#[Lazy]`，服务将立即创建。

详见[惰性代理]({{ '/zh/advanced.html' | relative_url }}#惰性代理)。

---

## 构建容器

配置完成后，调用 `build()` 创建运行时容器：

```php
$container = $builder->build();
```

`build()` 方法：
1. 将默认 lazy 模式应用于没有显式设置的定义
2. 检测不安全的循环依赖
3. 创建并返回 `Container` 实例

{: .note }
`build()` 会验证配置，对歧义绑定或不安全的循环依赖抛出异常。请在部署前修复。

---

## 下一步

- [属性]({{ '/zh/attributes.html' | relative_url }}) — PHP 属性声明式配置
- [环境变量]({{ '/zh/environment.html' | relative_url }}) — dotenv 和优先级
- [编译容器]({{ '/zh/compiled-container.html' | relative_url }}) — 生产环境优化
- [高级特性]({{ '/zh/advanced.html' | relative_url }}) — 自动配置、标签、惰性代理
