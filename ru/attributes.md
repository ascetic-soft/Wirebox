---
title: Атрибуты
layout: default
nav_order: 4
parent: Русский
---

# PHP-атрибуты
{: .no_toc }

Декларативная настройка сервисов с помощью нативных атрибутов PHP 8.4.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Обзор

Wirebox предоставляет набор PHP-атрибутов для декларативной настройки сервисов — прямо в определении класса. Внешние файлы конфигурации не нужны.

| Атрибут | Цель | Описание |
|:--------|:-----|:---------|
| `#[Singleton]` | Класс | Один экземпляр на контейнер (по умолчанию) |
| `#[Transient]` | Класс | Новый экземпляр при каждом `get()` |
| `#[Lazy]` | Класс | Отложенное создание через прокси |
| `#[Eager]` | Класс | Отказ от lazy-режима по умолчанию |
| `#[Tag]` | Класс | Тег для групповой выборки (повторяемый) |
| `#[Inject]` | Параметр | Переопределение type-hinted сервиса |
| `#[Param]` | Параметр | Инъекция переменной окружения |
| `#[Exclude]` | Класс | Пропуск при сканировании директорий |
| `#[AutoconfigureTag]` | Класс | Автоматическое тегирование по интерфейсу или атрибуту |

Все атрибуты находятся в пространстве имён `AsceticSoft\Wirebox\Attribute`.

---

## #[Singleton]

Помечает класс как синглтон. Поскольку это поведение по умолчанию, атрибут используется для явности:

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

**Эквивалент через fluent API:**

```php
$builder->register(DatabaseConnection::class)->singleton();
```

---

## #[Transient]

Новый экземпляр создаётся при каждом вызове `get()`:

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

Каждый вызов `$container->get(RequestContext::class)` возвращает новый экземпляр.

**Эквивалент через fluent API:**

```php
$builder->register(RequestContext::class)->transient();
```

{: .tip }
Используйте `#[Transient]` для сервисов с состоянием, специфичным для запроса: контекст запроса, данные формы, DTO.

---

## #[Lazy]

Немедленно возвращает легковесный прокси; реальный экземпляр создаётся только при первом обращении к свойству или методу. Использует нативные lazy objects PHP 8.4 (`ReflectionClass::newLazyProxy`):

```php
use AsceticSoft\Wirebox\Attribute\Lazy;

#[Lazy]
class HeavyReportGenerator
{
    public function __construct(
        private PDO $db,
        private CacheInterface $cache,
    ) {
        // тяжёлая инициализация — выполнится только когда реально понадобится
    }
}
```

**Ключевые особенности:**
- Прокси — реальный экземпляр класса (проходит проверку `instanceof`)
- Создание откладывается до первого обращения к свойству или методу
- Полностью поддерживается в компилируемом контейнере
- Можно комбинировать с `#[Transient]` для нового прокси при каждом `get()`

**Эквивалент через fluent API:**

```php
$builder->register(HeavyReportGenerator::class)->lazy();
```

{: .note }
Когда `defaultLazy` включён (по умолчанию), **все** сервисы становятся ленивыми, если не помечены `#[Eager]`. Атрибут `#[Lazy]` нужен только при выключенном `defaultLazy`.

---

## #[Eager]

Отказ от ленивого создания, когда lazy-режим контейнера включён по умолчанию:

```php
use AsceticSoft\Wirebox\Attribute\Eager;

#[Eager]
class AppConfig
{
    public function __construct()
    {
        // Всегда создаётся немедленно, даже при включённом defaultLazy
    }
}
```

Используйте для сервисов, которые:
- Должны инициализироваться рано (конфигурация, подписчики событий)
- Имеют побочные эффекты в конструкторе
- Должны проверять настройки при запуске

**Эквивалент через fluent API:**

```php
$builder->register(AppConfig::class)->eager();
```

---

## #[Tag]

Тегирование класса для групповой выборки. Атрибут **повторяемый** — класс может иметь несколько тегов:

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

Получение всех сервисов с тегом:

```php
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}
```

**Эквивалент через fluent API:**

```php
$builder->register(UserCreatedListener::class)->tag('event.listener', 'audit');
```

{: .tip }
Теги отлично подходят для систем плагинов, диспетчеров событий, цепочек middleware и паттернов command/query bus.

---

## #[AutoconfigureTag]

Автоматически тегирует все классы, реализующие интерфейс или декорированные пользовательским атрибутом. Размещается на самом **интерфейсе** или **атрибуте**.

### На интерфейсе

Все реализующие классы автоматически получают тег:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

// Автоматически получает тег 'command.handler' при сканировании
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void
    {
        // ...
    }
}
```

### На пользовательском атрибуте

Все классы с этим атрибутом получают тег:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
#[AutoconfigureTag('scheduler.task')]
class AsScheduled {}

// Автоматически получает тег 'scheduler.task' при сканировании
#[AsScheduled]
class DailyReportTask
{
    public function run(): void { /* ... */ }
}
```

### Несколько тегов

Атрибут повторяемый:

```php
#[AutoconfigureTag('command.handler')]
#[AutoconfigureTag('auditable')]
interface CommandHandlerInterface {}
```

{: .note }
Интерфейсы с `#[AutoconfigureTag]` исключаются из проверки на неоднозначную привязку — множественные реализации для них ожидаемы.

Также см. [Автоконфигурация]({{ '/ru/advanced.html' | relative_url }}#автоконфигурация) для программной настройки.

---

## #[Inject]

Переопределяет type-hinted сервис для конкретного параметра конструктора:

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

Без `#[Inject]` Wirebox разрешил бы `MailerInterface` через привязки контейнера. С `#[Inject]` всегда инжектится `SmtpMailer` независимо от привязок.

**Когда использовать:**
- Для одного сервиса нужна конкретная реализация, а для другого — другая
- Для интерфейса нет глобальной привязки
- Нужно переопределить глобальную привязку для конкретного потребителя

---

## #[Param]

Инъекция скалярного значения из переменных окружения прямо в параметр конструктора:

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

**Приведение типов** происходит автоматически на основе type hint параметра:

| PHP-тип | Значение из окружения | Результат |
|:--------|:---------------------|:----------|
| `string` | `"localhost"` | `"localhost"` |
| `int` | `"5432"` | `5432` |
| `float` | `"1.5"` | `1.5` |
| `bool` | `"true"` / `"1"` | `true` |
| `bool` | `"false"` / `"0"` / `""` | `false` |

{: .tip }
`#[Param]` читает напрямую из переменных окружения (с 3-уровневой системой приоритета). Это самый простой способ инъекции конфигурационных значений.

Подробнее: [Переменные окружения]({{ '/ru/environment.html' | relative_url }}).

---

## #[Exclude]

Исключает класс из авторегистрации при сканировании директорий:

```php
use AsceticSoft\Wirebox\Attribute\Exclude;

#[Exclude]
class InternalHelper
{
    // Не будет зарегистрирован в контейнере
}
```

**Когда использовать:**
- Вспомогательные/утилитные классы, которые не должны быть сервисами
- Базовые классы, не предназначенные для прямого создания
- Тестовые дубли, случайно попавшие в сканируемую директорию

{: .note }
`#[Exclude]` влияет только на сканирование. Исключённый класс можно зарегистрировать вручную через `$builder->register()`.

---

## Комбинирование атрибутов

Атрибуты свободно комбинируются:

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

## Атрибут vs Fluent API

У каждого атрибута есть эквивалент через fluent API. Используйте удобный вам стиль:

| Атрибут | Fluent API |
|:--------|:-----------|
| `#[Singleton]` | `->singleton()` |
| `#[Transient]` | `->transient()` |
| `#[Lazy]` | `->lazy()` |
| `#[Eager]` | `->eager()` |
| `#[Tag('x')]` | `->tag('x')` |
| `#[Exclude]` | `$builder->exclude(...)` |

{: .tip }
**Атрибуты** удобны, когда настройка логически принадлежит классу. **Fluent API** — для внешней или условной конфигурации (например, разные привязки для разных окружений).
