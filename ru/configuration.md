---
title: Конфигурация
layout: default
nav_order: 3
parent: Русский
---

# Конфигурация
{: .no_toc }

Всё, что нужно для настройки контейнера Wirebox.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## ContainerBuilder

`ContainerBuilder` — главная точка входа для настройки контейнера:

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);
```

Параметр `projectDir` используется как базовый путь для поиска файлов `.env`.

---

## Сканирование директорий

Сканируйте одну или несколько директорий для автоматической регистрации всех конкретных классов. Абстрактные классы, интерфейсы, трейты и перечисления (enum) автоматически пропускаются:

```php
$builder->scan(__DIR__ . '/src');
$builder->scan(__DIR__ . '/modules');
```

Сканер использует токенизатор PHP для быстрого и надёжного обнаружения классов без загрузки файлов.

### Исключение файлов

Исключайте файлы по glob-паттерну. Паттерны задаются относительно сканируемой директории:

```php
$builder->exclude('Entity/*');
$builder->exclude('*Test.php');
$builder->scan(__DIR__ . '/src');
```

{: .important }
Вызывайте `exclude()` **до** `scan()` — паттерны исключения применяются к последующим сканированиям.

Можно также исключить отдельные классы атрибутом `#[Exclude]`:

```php
use AsceticSoft\Wirebox\Attribute\Exclude;

#[Exclude]
class InternalHelper
{
    // Не будет зарегистрирован в контейнере
}
```

### Автопривязка интерфейсов

При сканировании, если у интерфейса **ровно одна** реализация в отсканированных директориях, Wirebox автоматически привязывает интерфейс к этой реализации.

Если найдено две или более реализации, привязка становится **неоднозначной**, и `build()` выбросит `ContainerException`. Разрешите неоднозначность явной привязкой через `bind()`:

```php
$builder->scan(__DIR__ . '/Services');
// У PaymentInterface есть StripePayment и PayPalPayment — неоднозначность!
$builder->bind(PaymentInterface::class, StripePayment::class);
```

{: .note }
Интерфейсы с атрибутом `#[AutoconfigureTag]` исключаются из проверки на неоднозначность, так как множественные реализации для них ожидаемы. См. [Автоконфигурация]({{ '/ru/advanced.html' | relative_url }}#автоконфигурация).

### Исключение интерфейсов из автопривязки

Если вам не нужна конкретная привязка, но вы хотите подавить ошибку неоднозначности (например, интерфейс разрешается в рантайме или используется только через итерацию по тегам), используйте `excludeFromAutoBinding()`:

```php
$builder->excludeFromAutoBinding(PaymentInterface::class);
$builder->scan(__DIR__ . '/Services');
// Нет ошибки — PaymentInterface исключён из проверки автопривязки
$container = $builder->build();
```

Можно исключить несколько интерфейсов за один вызов:

```php
$builder->excludeFromAutoBinding(
    PaymentInterface::class,
    NotificationChannelInterface::class,
);
```

{: .note }
В отличие от `registerForAutoconfiguration()`, этот метод не применяет никаких правил автоконфигурации (теги, время жизни и т.д.) — он только подавляет ошибку неоднозначности.

---

## Привязка интерфейсов

Явно привяжите интерфейс (или абстрактный класс) к конкретной реализации:

```php
$builder->bind(LoggerInterface::class, FileLogger::class);
$builder->bind(CacheInterface::class, RedisCache::class);
```

При разрешении зависимости по type hint контейнер сначала проверяет привязки, а затем пытается создать конкретный класс напрямую.

---

## Регистрация через фабрику

Зарегистрируйте сервис с фабричным замыканием для сложной логики создания:

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

Замыкание получает экземпляр `Container`, из которого можно получить другие сервисы и параметры.

{: .warning }
Фабричные замыкания **не поддерживаются** в компилируемом контейнере — они требуют runtime-выполнения. Где возможно, используйте автовайринг с атрибутами `#[Param]` или `#[Inject]`.

---

## Fluent Definition API

`register()` возвращает объект `Definition` с fluent-интерфейсом для тонкой настройки:

```php
$builder->register(FileLogger::class)
    ->transient()                                   // Новый экземпляр каждый раз
    ->lazy()                                        // Отложенное создание
    ->tag('logger')                                 // Добавить тег
    ->call('setFormatter', [JsonFormatter::class]);  // Setter-инъекция
```

### Доступные методы

| Метод | Описание |
|:------|:---------|
| `singleton()` | Один экземпляр на контейнер (по умолчанию) |
| `transient()` | Новый экземпляр при каждом вызове `get()` |
| `lazy()` | Возвращает прокси; реальный экземпляр создаётся при первом обращении |
| `eager()` | Всегда создаётся немедленно (отключает lazy по умолчанию) |
| `tag(string ...$tags)` | Добавить один или несколько тегов для групповой выборки |
| `call(string $method, array $args)` | Настройка setter-инъекции (вызов после конструктора) |

### Setter-инъекция

Настройте вызов методов после создания сервиса:

```php
$builder->register(Mailer::class)
    ->call('setTransport', [SmtpTransport::class])
    ->call('setLogger', [FileLogger::class]);
```

Аргументы могут быть:
- **Имена классов** — разрешаются из контейнера
- **Скалярные значения** — передаются как есть

---

## Параметры

Определяйте параметры, ссылающиеся на переменные окружения:

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');
$builder->parameter('rate.limit', '%env(float:RATE_LIMIT)%');
```

### Приведение типов

Поддерживаемые приведения внутри выражений `%env(...)%`:

| Тип | Пример | Результат |
|:----|:-------|:----------|
| `string` (по умолчанию) | `%env(DB_HOST)%` | `"localhost"` |
| `int` | `%env(int:DB_PORT)%` | `5432` |
| `float` | `%env(float:RATE_LIMIT)%` | `1.5` |
| `bool` | `%env(bool:APP_DEBUG)%` | `true` |

### Встроенные выражения

Выражения с переменными окружения можно встраивать в строки:

```php
$builder->parameter('dsn', 'mysql:host=%env(DB_HOST)%;port=%env(DB_PORT)%');
// Результат: "mysql:host=localhost;port=5432"
```

### Простые значения

Параметры могут быть обычными значениями без env-выражений:

```php
$builder->parameter('pagination.limit', 25);
$builder->parameter('app.name', 'My Application');
```

---

## Режим lazy по умолчанию

По умолчанию `ContainerBuilder` включает **lazy-режим** — все сервисы создаются как ленивые прокси, если у них нет явного атрибута `#[Eager]`. Обычно это то, что нужно для производительности.

Чтобы отключить lazy по умолчанию:

```php
$builder->defaultLazy(false);
```

При отключении сервисы создаются немедленно, если явно не помечены `#[Lazy]`.

Подробнее: [Ленивые прокси]({{ '/ru/advanced.html' | relative_url }}#ленивые-прокси).

---

## Сборка контейнера

После настройки вызовите `build()` для создания контейнера:

```php
$container = $builder->build();
```

Метод `build()`:
1. Применяет lazy-режим по умолчанию к определениям без явных настроек
2. Обнаруживает небезопасные циклические зависимости
3. Создаёт и возвращает экземпляр `Container`

{: .note }
`build()` валидирует конфигурацию и выбрасывает исключения при неоднозначных привязках или небезопасных циклических зависимостях. Исправьте их перед деплоем.

---

## Далее

- [Атрибуты]({{ '/ru/attributes.html' | relative_url }}) — декларативная настройка PHP-атрибутами
- [Переменные окружения]({{ '/ru/environment.html' | relative_url }}) — dotenv и уровни приоритета
- [Компилируемый контейнер]({{ '/ru/compiled-container.html' | relative_url }}) — оптимизация для продакшена
- [Продвинутые возможности]({{ '/ru/advanced.html' | relative_url }}) — автоконфигурация, теги, ленивые прокси
