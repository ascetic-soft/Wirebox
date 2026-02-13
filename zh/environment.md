---
title: 环境变量
layout: default
nav_order: 5
parent: 中文
---

# 环境变量
{: .no_toc }

内置 dotenv 支持，三级优先级与类型转换。
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>目录</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## 概览

Wirebox **内置** `.env` 解析器——无需 `vlucas/phpdotenv` 或 `symfony/dotenv` 等外部包。环境变量通过三级优先级系统解析。

---

## 解析优先级

变量按优先级顺序解析（从高到低）：

| 优先级 | 来源 | 说明 |
|:-------|:-----|:-----|
| **1**（最高） | `.env.local.php` | PHP 数组文件。由 `composer dump-env` 生成。最快。 |
| **2** | `$_ENV` / `getenv()` | 真实系统环境变量（Docker、CI 等） |
| **3**（最低） | `.env` | 由内置 `DotEnvParser` 解析。开发回退。 |

所有文件相对于传给 `ContainerBuilder` 的 `projectDir` 解析。

{: .note }
如果变量在多个级别定义，最高优先级的来源获胜。例如，系统环境变量覆盖 `.env`，而 `.env.local.php` 覆盖一切。

---

## `.env` 文件

在项目根目录创建 `.env` 文件作为开发默认值：

```env
# 应用
APP_NAME=Wirebox
APP_DEBUG=true

# 数据库
DB_HOST=localhost
DB_PORT=5432
DB_NAME=myapp

# 密钥
SECRET_KEY=dev-secret-key-change-in-production
```

### 支持的语法

```env
# 注释以 # 开头
APP_NAME=Wirebox

# 引号值（保留空格）
GREETING="Hello World"

# 单引号（字面量，不插值）
PATTERN='${NOT_INTERPOLATED}'

# 变量插值（双引号或无引号）
BASE_PATH=/opt
FULL_PATH="${BASE_PATH}/app"
ALSO_WORKS=${BASE_PATH}/app

# export 前缀会被去除
export SECRET_KEY=abc123

# 空值
EMPTY_VALUE=
```

### 插值规则

| 语法 | 行为 |
|:-----|:-----|
| 双引号中的 `${VAR}` | 插值 |
| 无引号的 `${VAR}` | 插值 |
| 单引号中的 `${VAR}` | **字面量**（不插值） |

---

## 使用环境变量

### 通过 `#[Param]` 属性

最简方式——直接注入到构造函数参数：

```php
use AsceticSoft\Wirebox\Attribute\Param;

class AppConfig
{
    public function __construct(
        #[Param('APP_NAME')] private string $appName,
        #[Param('APP_DEBUG')] private bool $debug,
        #[Param('DB_PORT')] private int $port,
    ) {
    }
}
```

类型转换根据参数类型提示自动进行。

### 通过构建器的 `parameter()`

定义带 env 表达式的命名参数：

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');
```

然后从容器获取：

```php
$host = $container->getParameter('db.host');
$port = $container->getParameter('db.port');  // int
```

---

## 类型转换

### 在 `parameter()` 表达式中

在 `%env(...)%` 中使用 `类型:` 前缀：

```php
$builder->parameter('port', '%env(int:DB_PORT)%');     // int
$builder->parameter('rate', '%env(float:RATE_LIMIT)%'); // float
$builder->parameter('debug', '%env(bool:APP_DEBUG)%');  // bool
$builder->parameter('host', '%env(DB_HOST)%');          // string（默认）
```

| 类型 | 表达式 | 输入 | 输出 |
|:-----|:-------|:-----|:-----|
| `string` | `%env(DB_HOST)%` | `"localhost"` | `"localhost"` |
| `int` | `%env(int:DB_PORT)%` | `"5432"` | `5432` |
| `float` | `%env(float:RATE)%` | `"1.5"` | `1.5` |
| `bool` | `%env(bool:DEBUG)%` | `"true"` | `true` |

### 布尔转换规则

| 值 | 结果 |
|:---|:-----|
| `"true"`、`"1"`、`"yes"`、`"on"` | `true` |
| `"false"`、`"0"`、`"no"`、`"off"`、`""` | `false` |

### 通过 `#[Param]` 属性

类型转换基于 PHP 类型提示：

```php
public function __construct(
    #[Param('DB_PORT')] private int $port,      // 转为 int
    #[Param('RATE')] private float $rate,        // 转为 float
    #[Param('DEBUG')] private bool $debug,       // 转为 bool
    #[Param('HOST')] private string $host,       // string（不转换）
)
```

---

## 嵌入表达式

Env 表达式可嵌入到字符串中：

```php
$builder->parameter('dsn', 'mysql:host=%env(DB_HOST)%;port=%env(DB_PORT)%');
// 结果: "mysql:host=localhost;port=5432"

$builder->parameter('redis.url', 'redis://%env(REDIS_HOST)%:%env(REDIS_PORT)%');
// 结果: "redis://127.0.0.1:6379"
```

{: .note }
当 env 表达式嵌入到字符串中时，结果始终是字符串——即使有类型转换前缀。类型转换仅在表达式是参数的全部值时生效。

---

## 生产环境：`.env.local.php`

生产部署时，避免运行时解析 `.env` 文件。生成缓存的 PHP 数组：

```bash
# 使用 Symfony 的 composer 插件
composer dump-env prod
```

这将创建 `.env.local.php`：

```php
<?php
return [
    'APP_NAME' => 'Wirebox',
    'APP_DEBUG' => 'false',
    'DB_HOST' => 'db.production.internal',
    'DB_PORT' => '5432',
];
```

由于是纯 PHP 数组，解析开销为零——只是一个文件包含。

{: .tip }
`.env.local.php` 文件具有**最高优先级**。它覆盖系统环境变量和 `.env`。这使部署可预测——导出什么就得到什么。

---

## 最佳实践

1. **不要提交**含真实密钥的 `.env`。使用 `.env.example` 作为模板。

2. **生产环境使用 `.env.local.php`** 实现零开销：
   ```bash
   composer dump-env prod
   ```

3. **在 Docker/CI 中使用系统环境变量**：
   ```dockerfile
   ENV DB_HOST=db.internal
   ENV DB_PORT=5432
   ```

4. **在边界处进行类型转换** —— 使用 `int:`、`float:`、`bool:` 前缀确保从一开始就是正确类型。

5. **保持 `.env` 精简** —— 只放开发默认值。生产配置应来自环境或 `.env.local.php`。
