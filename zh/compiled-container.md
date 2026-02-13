---
title: 编译容器
layout: default
nav_order: 6
parent: 中文
---

# 编译容器
{: .no_toc }

零反射的生产环境容器，实现最大性能。
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>目录</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## 概览

开发环境中，Wirebox 使用反射解析依赖。这对开发足够快，但在生产环境有额外开销。**编译容器**生成一个纯 PHP 类，为每个服务提供专用工厂方法——**运行时零反射**。

---

## 生成编译容器

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);

// 照常配置
$builder->exclude('Entity/*');
$builder->scan(__DIR__ . '/src');
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');

// 生成编译容器
$builder->compile(
    outputPath: __DIR__ . '/var/cache/CompiledContainer.php',
    className: 'CompiledContainer',
    namespace: 'App\Cache',
);
```

### 参数

| 参数 | 说明 |
|:-----|:-----|
| `outputPath` | 生成的 PHP 类文件路径 |
| `className` | 生成的类名 |
| `namespace` | 生成类的 PHP 命名空间 |

---

## 使用编译容器

生产环境中，引入生成的文件并直接实例化：

```php
require_once __DIR__ . '/var/cache/CompiledContainer.php';

$container = new App\Cache\CompiledContainer();

// 使用方式与运行时容器完全相同
$service = $container->get(UserService::class);
$loggers = $container->getTagged('logger');
$host = $container->getParameter('db.host');
```

编译容器实现 `Psr\Container\ContainerInterface`，支持与运行时容器相同的 API。

---

## 编译内容

生成的类包含：

- **工厂方法** — 每个服务一个专用的 `create_*()` 方法
- **单例缓存** — 服务在首次创建后缓存
- **绑定映射** — 接口到实现的映射
- **参数** — 所有定义的参数及解析后的 env 表达式
- **标签** — 用于 `getTagged()` 的标签服务分组
- **惰性代理** — 通过 `ReflectionClass::newLazyProxy()` 延迟实例化
- **Setter 注入** — 通过 `call()` 配置的方法调用

---

## 开发 vs 生产工作流

### 推荐方案

```php
// bootstrap.php
use AsceticSoft\Wirebox\ContainerBuilder;

$compiledPath = __DIR__ . '/var/cache/CompiledContainer.php';

if (file_exists($compiledPath)) {
    // 生产：编译容器
    require_once $compiledPath;
    $container = new App\Cache\CompiledContainer();
} else {
    // 开发：运行时容器
    $builder = new ContainerBuilder(projectDir: __DIR__);
    $builder->scan(__DIR__ . '/src');
    $builder->bind(LoggerInterface::class, FileLogger::class);
    $builder->parameter('db.host', '%env(DB_HOST)%');
    $container = $builder->build();
}
```

### 构建脚本

创建部署脚本：

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

echo "容器编译成功。\n";
```

部署时运行：

```bash
php bin/compile-container.php
```

---

## 限制

{: .warning }
编译容器**不支持工厂闭包**。闭包无法序列化为 PHP 代码。请改用带属性的自动装配。

```php
// 这不会包含在编译容器中
$builder->register(PDO::class, function ($c) {
    return new PDO(...);
});

// 改用这种方式
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

## 编译容器中的惰性代理

惰性代理完全支持。生成的工厂方法使用 `ReflectionClass::newLazyProxy()`：

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

代理行为与运行时容器的惰性行为完全一致。

---

## 最佳实践

1. **服务变更后务必重新生成**编译容器
2. **添加到 `.gitignore`** — 不要提交生成的文件：
   ```
   /var/cache/CompiledContainer.php
   ```
3. **部署时编译** — 添加到 CI/CD 流水线：
   ```bash
   php bin/compile-container.php
   composer dump-env prod
   ```
4. **编译前验证** — `compile()` 执行与 `build()` 相同的检查，包括循环依赖检测
5. **共享配置** — 将构建器配置提取到函数或配置文件中，避免开发和编译之间重复
