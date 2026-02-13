---
title: Компилируемый контейнер
layout: default
nav_order: 6
parent: Русский
---

# Компилируемый контейнер
{: .no_toc }

Контейнер без рефлексии для максимальной производительности в продакшене.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Обзор

В разработке Wirebox использует рефлексию для разрешения зависимостей. Для разработки этого достаточно, но в продакшене это лишние накладные расходы. **Компилируемый контейнер** генерирует обычный PHP-класс с фабричными методами для каждого сервиса — **нулевая рефлексия в рантайме**.

---

## Генерация компилируемого контейнера

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);

// Настраиваем как обычно
$builder->exclude('Entity/*');
$builder->scan(__DIR__ . '/src');
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');

// Генерируем компилируемый контейнер
$builder->compile(
    outputPath: __DIR__ . '/var/cache/CompiledContainer.php',
    className: 'CompiledContainer',
    namespace: 'App\Cache',
);
```

### Параметры

| Параметр | Описание |
|:---------|:---------|
| `outputPath` | Путь к файлу генерируемого PHP-класса |
| `className` | Имя генерируемого класса |
| `namespace` | PHP-пространство имён для генерируемого класса |

---

## Использование компилируемого контейнера

В продакшене подключите сгенерированный файл и создайте экземпляр напрямую:

```php
require_once __DIR__ . '/var/cache/CompiledContainer.php';

$container = new App\Cache\CompiledContainer();

// Используется точно так же, как runtime-контейнер
$service = $container->get(UserService::class);
$loggers = $container->getTagged('logger');
$host = $container->getParameter('db.host');
```

Компилируемый контейнер реализует `Psr\Container\ContainerInterface` и поддерживает тот же API, что и runtime-контейнер.

---

## Что попадает в компиляцию

Генерируемый класс включает:

- **Фабричные методы** — отдельный метод `create_*()` для каждого сервиса
- **Кеширование синглтонов** — сервисы кешируются после первого создания
- **Карта привязок** — маппинг интерфейсов на реализации
- **Параметры** — все определённые параметры с разрешёнными env-выражениями
- **Теги** — группы тегированных сервисов для `getTagged()`
- **Ленивые прокси** — отложенное создание через `ReflectionClass::newLazyProxy()`
- **Setter-инъекция** — вызовы методов, настроенные через `call()`

---

## Пример: сгенерированный код

Для простой конфигурации:

```php
$builder->scan(__DIR__ . '/src');
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->parameter('db.host', '%env(DB_HOST)%');
```

Компилятор генерирует что-то вроде:

```php
namespace App\Cache;

class CompiledContainer extends \AsceticSoft\Wirebox\Compiler\CompiledContainer
{
    protected function getBindings(): array
    {
        return [
            \App\LoggerInterface::class => \App\FileLogger::class,
        ];
    }

    protected function getParameterDefinitions(): array
    {
        return [
            'db.host' => '%env(DB_HOST)%',
        ];
    }

    protected function getTagMap(): array
    {
        return [];
    }

    protected function createAppFileLogger(): \App\FileLogger
    {
        return new \App\FileLogger();
    }

    protected function createAppUserService(): \App\UserService
    {
        return new \App\UserService(
            $this->get(\App\LoggerInterface::class),
        );
    }
}
```

---

## Схема разработка vs продакшен

### Рекомендуемый подход

```php
// bootstrap.php
use AsceticSoft\Wirebox\ContainerBuilder;

$compiledPath = __DIR__ . '/var/cache/CompiledContainer.php';

if (file_exists($compiledPath)) {
    // Продакшен: компилируемый контейнер
    require_once $compiledPath;
    $container = new App\Cache\CompiledContainer();
} else {
    // Разработка: runtime-контейнер
    $builder = new ContainerBuilder(projectDir: __DIR__);
    $builder->scan(__DIR__ . '/src');
    $builder->bind(LoggerInterface::class, FileLogger::class);
    $builder->parameter('db.host', '%env(DB_HOST)%');
    $container = $builder->build();
}
```

### Скрипт сборки

Создайте скрипт для деплоя:

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

echo "Контейнер успешно скомпилирован.\n";
```

Запуск при деплое:

```bash
php bin/compile-container.php
```

---

## Ограничения

{: .warning }
**Фабричные замыкания не поддерживаются** в компилируемом контейнере. Замыкания нельзя сериализовать в PHP-код. Используйте автовайринг с атрибутами.

```php
// Это НЕ попадёт в компилируемый контейнер
$builder->register(PDO::class, function ($c) {
    return new PDO(...);
});

// Используйте вместо этого
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

## Ленивые прокси в компилируемом контейнере

Ленивые прокси полностью поддерживаются. Сгенерированные фабричные методы используют `ReflectionClass::newLazyProxy()`:

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

Прокси ведёт себя идентично lazy-поведению runtime-контейнера.

---

## Лучшие практики

1. **Всегда перегенерируйте** компилируемый контейнер при изменении сервисов
2. **Добавьте в `.gitignore`** — не коммитьте сгенерированный файл:
   ```
   /var/cache/CompiledContainer.php
   ```
3. **Компилируйте при деплое** — добавьте в CI/CD пайплайн:
   ```bash
   php bin/compile-container.php
   composer dump-env prod
   ```
4. **Валидация перед компиляцией** — `compile()` выполняет те же проверки, что и `build()`, включая обнаружение циклических зависимостей
5. **Общая конфигурация** — вынесите настройку билдера в функцию или конфиг-файл, чтобы не дублировать между разработкой и компиляцией
