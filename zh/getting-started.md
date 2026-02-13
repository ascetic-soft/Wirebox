---
title: 快速开始
layout: default
nav_order: 2
parent: 中文
---

# 快速开始
{: .no_toc }

5 分钟上手 Wirebox。
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>目录</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## 安装

通过 Composer 安装 Wirebox：

```bash
composer require ascetic-soft/wirebox
```

**环境要求：**
- PHP >= 8.4
- psr/container ^2.0

---

## 创建第一个容器

### 第一步：创建服务

```php
// src/Mailer.php
namespace App;

class Mailer
{
    public function send(string $to, string $message): void
    {
        // 发送邮件...
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
        // 创建用户...
        $this->mailer->send($email, '欢迎！');
    }
}
```

### 第二步：构建容器

```php
// bootstrap.php
use AsceticSoft\Wirebox\ContainerBuilder;

require_once __DIR__ . '/vendor/autoload.php';

$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();
```

### 第三步：获取并使用服务

```php
$userService = $container->get(App\UserService::class);
$userService->register('user@example.com');
```

Wirebox 自动将 `Mailer` 作为 `UserService` 的依赖进行解析——无需手动配置。

---

## 自动装配原理

当你调用 `$container->get(UserService::class)` 时，Wirebox：

1. 通过反射检查 `UserService` 的构造函数
2. 发现需要一个 `Mailer` 实例
3. 递归解析 `Mailer`（必要时创建）
4. 将其注入 `UserService` 的构造函数
5. 返回完全构造好的 `UserService`

一切自动完成，无需配置文件或手动绑定。

{: .note }
默认情况下，所有服务都是**单例**——每次调用 `get()` 返回相同的实例。使用 `#[Transient]` 属性可以在每次获取时创建新实例。

---

## 使用接口

当代码依赖接口时，如果只有一个实现类，Wirebox 会自动绑定：

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

由于 `FileLogger` 是扫描目录中 `LoggerInterface` 的唯一实现，Wirebox 自动绑定该接口：

```php
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

$orderService = $container->get(App\OrderService::class);
// LoggerInterface 自动绑定到 FileLogger
```

如果有多个实现，则需要显式绑定：

```php
$builder->bind(App\LoggerInterface::class, App\FileLogger::class);
```

---

## 添加环境变量

在项目根目录创建 `.env` 文件：

```env
DB_HOST=localhost
DB_PORT=5432
APP_DEBUG=true
```

使用 `#[Param]` 属性注入环境变量：

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

或通过构建器定义参数：

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
```

---

## 完整 Bootstrap 示例

```php
<?php
// bootstrap.php

use AsceticSoft\Wirebox\ContainerBuilder;

require_once __DIR__ . '/vendor/autoload.php';

$builder = new ContainerBuilder(projectDir: __DIR__);

// 排除非服务类
$builder->exclude('Entity/*');
$builder->exclude('Migration/*');

// 自动注册 src/ 下所有类
$builder->scan(__DIR__ . '/src');

// 歧义接口的显式绑定
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->bind(CacheInterface::class, RedisCache::class);

// 环境参数
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');

// 复杂实例化的工厂
$builder->register(PDO::class, function ($c) {
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=app',
            $c->getParameter('db.host'),
            $c->getParameter('db.port'),
        ),
    );
});

// 构建并运行
$container = $builder->build();
$app = $container->get(App\Kernel::class);
$app->run();
```

---

## 下一步

- [配置]({{ '/zh/configuration.html' | relative_url }}) — 目录扫描、绑定、工厂和 Fluent API
- [属性]({{ '/zh/attributes.html' | relative_url }}) — 所有 PHP 属性完整参考
- [环境变量]({{ '/zh/environment.html' | relative_url }}) — dotenv、优先级和类型转换
- [编译容器]({{ '/zh/compiled-container.html' | relative_url }}) — 生产环境零反射容器
- [高级特性]({{ '/zh/advanced.html' | relative_url }}) — 自动配置、标签、惰性代理等
