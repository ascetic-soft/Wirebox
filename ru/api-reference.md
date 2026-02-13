---
title: Справочник API
layout: default
nav_order: 8
parent: Русский
---

# Справочник API
{: .no_toc }

Полный справочник по всем публичным классам и методам.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## ContainerBuilder

Главная точка входа для настройки и сборки DI-контейнера.

```php
use AsceticSoft\Wirebox\ContainerBuilder;
```

### Конструктор

```php
new ContainerBuilder(string $projectDir)
```

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `$projectDir` | `string` | Базовая директория для поиска файлов `.env` |

### Методы

#### `scan(string $directory): void`

Рекурсивно сканирует директорию и автоматически регистрирует все конкретные классы. Абстрактные классы, интерфейсы, трейты и enum пропускаются. Классы с `#[Exclude]` тоже.

```php
$builder->scan(__DIR__ . '/src');
```

Если у интерфейса ровно одна реализация — он автоматически привязывается. При множественных реализациях возникает ошибка неоднозначности (если не разрешена через `bind()`).

---

#### `exclude(string $pattern): void`

Исключает файлы по glob-паттерну из последующих сканирований. Паттерны задаются относительно сканируемой директории.

```php
$builder->exclude('Entity/*');
$builder->exclude('*Test.php');
```

{: .important }
Должен вызываться **до** `scan()`.

---

#### `bind(string $abstract, string $concrete): void`

Привязывает интерфейс или абстрактный класс к конкретной реализации.

```php
$builder->bind(LoggerInterface::class, FileLogger::class);
```

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `$abstract` | `string` | FQCN интерфейса или абстрактного класса |
| `$concrete` | `string` | FQCN конкретного класса |

---

#### `register(string $id, ?Closure $factory = null): Definition`

Регистрирует сервис по ID, опционально с фабричным замыканием. Возвращает `Definition` для fluent-настройки.

```php
// С фабрикой
$builder->register(PDO::class, fn($c) => new PDO(...));

// Без фабрики (для fluent-настройки)
$builder->register(Mailer::class)
    ->transient()
    ->tag('mail');
```

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `$id` | `string` | Идентификатор сервиса (обычно FQCN) |
| `$factory` | `?Closure` | Опциональное замыкание-фабрика, получающее `Container` |

**Возвращает:** `Definition`

---

#### `parameter(string $name, mixed $value): void`

Определяет именованный параметр. Значение может содержать выражения `%env(...)%`.

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.name', 'My App');
```

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `$name` | `string` | Имя параметра |
| `$value` | `mixed` | Значение (простое или с env-выражениями) |

---

#### `defaultLazy(bool $lazy): void`

Устанавливает lazy-режим по умолчанию. При включении (по умолчанию) все сервисы становятся ленивыми прокси, если не помечены `#[Eager]`.

```php
$builder->defaultLazy(false); // Отключить lazy по умолчанию
```

---

#### `registerForAutoconfiguration(string $classOrInterface): AutoconfigureRule`

Регистрирует правило автоконфигурации для интерфейса или атрибута. Возвращает `AutoconfigureRule` для fluent-настройки.

```php
$builder->registerForAutoconfiguration(EventListenerInterface::class)
    ->tag('event.listener')
    ->singleton()
    ->lazy();
```

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `$classOrInterface` | `string` | FQCN интерфейса или атрибута |

**Возвращает:** `AutoconfigureRule`

---

#### `build(): Container`

Собирает и возвращает runtime-контейнер. Валидирует конфигурацию, обнаруживает циклические зависимости, применяет lazy-режим по умолчанию.

```php
$container = $builder->build();
```

**Возвращает:** `Container`

**Выбрасывает:** `ContainerException`, `CircularDependencyException`

---

#### `compile(string $outputPath, string $className, string $namespace): void`

Генерирует PHP-класс компилируемого контейнера. Выполняет ту же валидацию, что и `build()`.

```php
$builder->compile(
    outputPath: __DIR__ . '/var/cache/CompiledContainer.php',
    className: 'CompiledContainer',
    namespace: 'App\Cache',
);
```

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `$outputPath` | `string` | Путь к генерируемому файлу |
| `$className` | `string` | Имя генерируемого класса |
| `$namespace` | `string` | Пространство имён генерируемого класса |

**Выбрасывает:** `ContainerException`, `CircularDependencyException`

---

## Container

Runtime-контейнер внедрения зависимостей. Реализует `Psr\Container\ContainerInterface`.

```php
use AsceticSoft\Wirebox\Container;
```

### Методы

#### `get(string $id): mixed`

Разрешает и возвращает сервис по ID. Синглтоны кешируются после первого создания.

```php
$service = $container->get(UserService::class);
```

**Выбрасывает:** `NotFoundException`, `AutowireException`, `CircularDependencyException`

---

#### `has(string $id): bool`

Проверяет, может ли сервис быть разрешён.

```php
if ($container->has(UserService::class)) {
    // ...
}
```

---

#### `getTagged(string $tag): iterable`

Возвращает итератор всех сервисов с данным тегом. Сервисы разрешаются лениво при итерации.

```php
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}
```

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `$tag` | `string` | Имя тега |

**Возвращает:** `iterable<object>`

---

#### `getParameter(string $name): mixed`

Получает значение параметра. Env-выражения разрешаются при первом обращении.

```php
$host = $container->getParameter('db.host');
```

**Выбрасывает:** `ContainerException` (если параметр не найден)

---

#### `getParameters(): array`

Возвращает все параметры как ассоциативный массив.

```php
$params = $container->getParameters();
// ['db.host' => 'localhost', 'db.port' => 5432, ...]
```

---

## Definition

Fluent-билдер для настройки определения сервиса. Возвращается `ContainerBuilder::register()`.

```php
use AsceticSoft\Wirebox\Definition;
```

### Методы

#### `singleton(): self`

Настраивает как синглтон (один экземпляр на контейнер). Это поведение по умолчанию.

```php
$builder->register(Service::class)->singleton();
```

---

#### `transient(): self`

Настраивает как transient (новый экземпляр при каждом `get()`).

```php
$builder->register(Service::class)->transient();
```

---

#### `lazy(): self`

Включает ленивый прокси. Реальный экземпляр создаётся при первом обращении.

```php
$builder->register(Service::class)->lazy();
```

---

#### `eager(): self`

Отключает ленивый прокси. Экземпляр создаётся немедленно.

```php
$builder->register(Service::class)->eager();
```

---

#### `tag(string ...$tags): self`

Добавляет один или несколько тегов.

```php
$builder->register(Service::class)->tag('logger', 'audit');
```

---

#### `call(string $method, array $arguments = []): self`

Настраивает вызов метода после создания (setter-инъекция).

```php
$builder->register(Service::class)
    ->call('setLogger', [FileLogger::class])
    ->call('setDebug', [true]);
```

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `$method` | `string` | Имя вызываемого метода |
| `$arguments` | `array` | Аргументы (имена классов разрешаются, скалярные — как есть) |

---

## AutoconfigureRule

Fluent-билдер для правил автоконфигурации. Возвращается `ContainerBuilder::registerForAutoconfiguration()`.

```php
use AsceticSoft\Wirebox\AutoconfigureRule;
```

### Методы

| Метод | Описание |
|:------|:---------|
| `tag(string ...$tags): self` | Добавить теги подходящим сервисам |
| `singleton(): self` | Установить синглтон для подходящих сервисов |
| `transient(): self` | Установить transient для подходящих сервисов |
| `lazy(): self` | Включить lazy-режим для подходящих сервисов |
| `eager(): self` | Отключить lazy-режим для подходящих сервисов |

---

## Lifetime

Перечисление (enum) времени жизни сервиса.

```php
use AsceticSoft\Wirebox\Lifetime;
```

| Значение | Описание |
|:---------|:---------|
| `Lifetime::Singleton` | Один экземпляр на контейнер |
| `Lifetime::Transient` | Новый экземпляр каждый раз |

---

## Исключения

Все исключения находятся в пространстве имён `AsceticSoft\Wirebox\Exception` и реализуют `Psr\Container\ContainerExceptionInterface`.

### NotFoundException

Выбрасывается, когда сервис не найден и не может быть автовайрен. Реализует `Psr\Container\NotFoundExceptionInterface`.

### AutowireException

Выбрасывается, когда параметр конструктора не может быть разрешён (нет type hint, неразрешимый тип, отсутствует обязательный параметр).

### CircularDependencyException

Выбрасывается при обнаружении небезопасной циклической зависимости. Сообщение включает:
- Полный путь цикла (например, `ServiceA -> ServiceB -> ServiceA`)
- Какие сервисы небезопасны и почему

### ContainerException

Общая ошибка контейнера. Типичные причины:
- Неоднозначная автопривязка (несколько реализаций, нет явного `bind()`)
- Неверная конфигурация
- Параметр не найден

---

## Сводка по атрибутам

Все атрибуты находятся в пространстве имён `AsceticSoft\Wirebox\Attribute`.

| Атрибут | Цель | Повторяемый | Описание |
|:--------|:-----|:------------|:---------|
| `#[Singleton]` | Класс | Нет | Время жизни — синглтон |
| `#[Transient]` | Класс | Нет | Время жизни — transient |
| `#[Lazy]` | Класс | Нет | Ленивый прокси |
| `#[Eager]` | Класс | Нет | Отказ от lazy |
| `#[Tag('name')]` | Класс | Да | Добавить тег |
| `#[Inject(Class::class)]` | Параметр | Нет | Переопределить type hint |
| `#[Param('ENV_VAR')]` | Параметр | Нет | Инъекция переменной окружения |
| `#[Exclude]` | Класс | Нет | Пропуск при сканировании |
| `#[AutoconfigureTag('tag')]` | Класс | Да | Автотегирование реализаций |
