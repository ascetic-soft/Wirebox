---
title: Быстрый старт
layout: default
nav_order: 2
parent: Русский
---

# Быстрый старт
{: .no_toc }

Начните работу с Wirebox за 5 минут.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Установка

Установите Wirebox через Composer:

```bash
composer require ascetic-soft/wirebox
```

**Требования:**
- PHP >= 8.4
- psr/container ^2.0

---

## Ваш первый контейнер

### Шаг 1: Создайте сервисы

```php
// src/Mailer.php
namespace App;

class Mailer
{
    public function send(string $to, string $message): void
    {
        // отправка письма...
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
        // создание пользователя...
        $this->mailer->send($email, 'Добро пожаловать!');
    }
}
```

### Шаг 2: Соберите контейнер

```php
// bootstrap.php
use AsceticSoft\Wirebox\ContainerBuilder;

require_once __DIR__ . '/vendor/autoload.php';

$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();
```

### Шаг 3: Получите и используйте сервис

```php
$userService = $container->get(App\UserService::class);
$userService->register('user@example.com');
```

Wirebox автоматически разрешает `Mailer` как зависимость `UserService` — ручная настройка не нужна.

---

## Как работает автовайринг

Когда вы вызываете `$container->get(UserService::class)`, Wirebox:

1. Анализирует конструктор `UserService` через рефлексию
2. Видит, что нужен экземпляр `Mailer`
3. Рекурсивно разрешает `Mailer` (создаёт, если нужно)
4. Передаёт его в конструктор `UserService`
5. Возвращает полностью собранный `UserService`

Всё происходит автоматически. Никаких файлов конфигурации и ручных привязок.

{: .note }
По умолчанию все сервисы — **синглтоны**: при каждом вызове `get()` возвращается один и тот же экземпляр. Используйте атрибут `#[Transient]`, чтобы получать новый экземпляр каждый раз.

---

## Работа с интерфейсами

Если ваш код зависит от интерфейсов, Wirebox автоматически привяжет их, если есть ровно одна реализация:

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

Поскольку `FileLogger` — единственная реализация `LoggerInterface` в отсканированной директории, Wirebox привяжет интерфейс автоматически:

```php
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

$orderService = $container->get(App\OrderService::class);
// LoggerInterface автоматически привязан к FileLogger
```

Если реализаций несколько, нужна явная привязка:

```php
$builder->bind(App\LoggerInterface::class, App\FileLogger::class);
```

---

## Переменные окружения

Создайте файл `.env` в корне проекта:

```env
DB_HOST=localhost
DB_PORT=5432
APP_DEBUG=true
```

Используйте атрибут `#[Param]` для инъекции переменных окружения:

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

Или определите параметры через билдер:

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
```

---

## Полный пример bootstrap

```php
<?php
// bootstrap.php

use AsceticSoft\Wirebox\ContainerBuilder;

require_once __DIR__ . '/vendor/autoload.php';

$builder = new ContainerBuilder(projectDir: __DIR__);

// Исключаем не-сервисные классы
$builder->exclude('Entity/*');
$builder->exclude('Migration/*');

// Автоматическая регистрация всех классов в src/
$builder->scan(__DIR__ . '/src');

// Явные привязки для неоднозначных интерфейсов
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->bind(CacheInterface::class, RedisCache::class);

// Параметры из окружения
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');

// Фабрика для сложного создания
$builder->register(PDO::class, function ($c) {
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=app',
            $c->getParameter('db.host'),
            $c->getParameter('db.port'),
        ),
    );
});

// Собираем и запускаем
$container = $builder->build();
$app = $container->get(App\Kernel::class);
$app->run();
```

---

## Что дальше?

- [Конфигурация]({{ '/ru/configuration.html' | relative_url }}) — сканирование директорий, привязки, фабрики и fluent API
- [Атрибуты]({{ '/ru/attributes.html' | relative_url }}) — полный справочник по всем PHP-атрибутам
- [Переменные окружения]({{ '/ru/environment.html' | relative_url }}) — dotenv, приоритеты и приведение типов
- [Компилируемый контейнер]({{ '/ru/compiled-container.html' | relative_url }}) — оптимизация для продакшена
- [Продвинутые возможности]({{ '/ru/advanced.html' | relative_url }}) — автоконфигурация, теги, ленивые прокси и другое
