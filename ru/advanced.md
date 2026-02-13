---
title: Продвинутые возможности
layout: default
nav_order: 7
parent: Русский
---

# Продвинутые возможности
{: .no_toc }

Автоконфигурация, теги, ленивые прокси, циклические зависимости и обработка ошибок.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Автоконфигурация

Автоконфигурация позволяет автоматически тегировать и настраивать сервисы на основе реализуемых интерфейсов или применённых атрибутов — аналогично автоконфигурации Symfony.

### Декларативная: `#[AutoconfigureTag]`

Разместите атрибут `#[AutoconfigureTag]` на **интерфейсе** или **пользовательском атрибуте**, чтобы автоматически тегировать все реализующие/декорированные классы.

#### На интерфейсе

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

// Автоматически тегируется как 'command.handler'
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

class DeleteUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}
```

#### На пользовательском атрибуте

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
#[AutoconfigureTag('scheduler.task')]
class AsScheduled {}

// Автоматически тегируется как 'scheduler.task'
#[AsScheduled]
class DailyReportTask
{
    public function run(): void { /* ... */ }
}

#[AsScheduled]
class CleanupTask
{
    public function run(): void { /* ... */ }
}
```

### Программная: `registerForAutoconfiguration()`

Для более тонкой настройки (время жизни, lazy-режим, множественные теги):

```php
$builder->registerForAutoconfiguration(EventListenerInterface::class)
    ->tag('event.listener')
    ->singleton()
    ->lazy();
```

Любой класс, реализующий `EventListenerInterface`, найденный при `scan()`, автоматически:
- Получит тег `event.listener`
- Будет настроен как синглтон
- Будет использовать ленивые прокси

Работает и с пользовательскими атрибутами:

```php
$builder->registerForAutoconfiguration(AsScheduled::class)
    ->tag('scheduler.task')
    ->transient();
```

### Пример CQRS

Полная настройка command/query bus с автоконфигурацией:

```php
use AsceticSoft\Wirebox\Attribute\AutoconfigureTag;

// Определяем интерфейсы обработчиков с автотегированием
#[AutoconfigureTag('command.handler')]
interface CommandHandlerInterface
{
    public function __invoke(object $command): void;
}

#[AutoconfigureTag('query.handler')]
interface QueryHandlerInterface
{
    public function __invoke(object $query): mixed;
}

// Обработчики команд — автоматически тегируются
class CreateUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

class DeleteUserHandler implements CommandHandlerInterface
{
    public function __invoke(object $command): void { /* ... */ }
}

// Обработчики запросов — автоматически тегируются
class GetUserHandler implements QueryHandlerInterface
{
    public function __invoke(object $query): mixed { /* ... */ }
}
```

Сборка и использование:

```php
$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

// Все обработчики команд
foreach ($container->getTagged('command.handler') as $handler) {
    // CreateUserHandler, DeleteUserHandler
}

// Все обработчики запросов
foreach ($container->getTagged('query.handler') as $handler) {
    // GetUserHandler
}
```

{: .note }
Автосконфигурированные интерфейсы исключаются из проверки на неоднозначную привязку. Несколько реализаций `CommandHandlerInterface` не вызовут ошибку — они предполагают выборку через теги.

---

## Тегированные сервисы

Теги группируют сервисы для коллективной выборки. Они необходимы для паттернов вроде диспетчеризации событий, цепочек middleware и систем плагинов.

### Добавление тегов

Через атрибут:

```php
use AsceticSoft\Wirebox\Attribute\Tag;

#[Tag('event.listener')]
class OrderCreatedListener { /* ... */ }

#[Tag('event.listener')]
class UserCreatedListener { /* ... */ }

#[Tag('event.listener')]
#[Tag('audit')]
class AuditListener { /* ... */ }
```

Через fluent API:

```php
$builder->register(OrderCreatedListener::class)->tag('event.listener');
$builder->register(AuditListener::class)->tag('event.listener', 'audit');
```

### Получение тегированных сервисов

```php
// Все слушатели событий
foreach ($container->getTagged('event.listener') as $listener) {
    $listener->handle($event);
}

// Все аудит-сервисы
foreach ($container->getTagged('audit') as $service) {
    // ...
}
```

### Пример диспетчера событий

```php
class EventDispatcher
{
    /** @var iterable<EventListenerInterface> */
    private iterable $listeners;

    public function __construct(ContainerInterface $container)
    {
        // Ленивые — слушатели не создаются до итерации
        $this->listeners = $container->getTagged('event.listener');
    }

    public function dispatch(object $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->handle($event);
        }
    }
}
```

---

## Ленивые прокси

Ленивые прокси откладывают создание сервиса до момента его фактического использования. Работают на нативных `ReflectionClass::newLazyProxy()` PHP 8.4.

### Как это работает

1. При запросе ленивого сервиса немедленно возвращается **прокси-объект**
2. Прокси — реальный экземпляр класса (проходит `instanceof`)
3. При обращении к свойству или методу создаётся **реальный экземпляр**
4. Последующие обращения используют уже созданный экземпляр

### Lazy-режим по умолчанию

По умолчанию `ContainerBuilder` включает lazy-режим. Все сервисы ленивые, кроме:
- Явно помеченных `#[Eager]`
- Настроенных через `->eager()` в fluent API

```php
// Все сервисы в этом контейнере ленивые по умолчанию
$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();
```

Для отключения:

```php
$builder->defaultLazy(false);
// Теперь ленивыми будут только сервисы с #[Lazy]
```

### Выигрыш в производительности

Ленивые прокси особенно полезны, когда:
- Сервисы имеют **тяжёлые конструкторы** (подключения к БД, HTTP-клиенты)
- У вас **много сервисов**, но в каждом запросе используются лишь несколько
- Сервисы имеют **сложные цепочки зависимостей**, которые нужны не всегда

```php
#[Lazy]
class ElasticsearchClient
{
    public function __construct()
    {
        // Дорого: установка соединения, проверка здоровья кластера
        $this->client = ClientBuilder::create()
            ->setHosts(['elasticsearch:9200'])
            ->build();
    }
}

// Прокси возвращается мгновенно — соединения нет
$client = $container->get(ElasticsearchClient::class);

// Соединение устанавливается только сейчас
$client->search(['index' => 'products', 'body' => [...]]);
```

---

## Циклические зависимости

Wirebox обнаруживает циклические зависимости **во время сборки** — до того, как контейнер начнёт использоваться.

### Безопасные циклы

Циклическая зависимость безопасна **только** когда **все** сервисы в цикле — **ленивые синглтоны**:

```php
#[Lazy]
class ServiceA
{
    public function __construct(public readonly ServiceB $b) {}
}

#[Lazy]
class ServiceB
{
    public function __construct(public readonly ServiceA $a) {}
}

$container = $builder->build(); // OK — оба ленивые синглтоны

$a = $container->get(ServiceA::class);
assert($a->b->a === $a); // Тот же прокси-экземпляр
```

**Почему это работает:** прокси кешируется в хранилище синглтонов до создания реального экземпляра. Когда цепочка зависимостей замыкается, она находит прокси в кеше, а не входит в повторное создание.

### Небезопасные циклы

| Сценарий | Результат |
|:---------|:----------|
| Все сервисы — ленивые синглтоны | **Безопасно** — прокси кешируется до создания |
| Любой сервис **eager** | **Небезопасно** — Autowirer обращается к тому же классу дважды |
| Любой сервис — **ленивый transient** | **Небезопасно** — прокси не кешируется, бесконечная рекурсия |

Небезопасные циклы дают понятные сообщения об ошибках:

```
Circular dependency detected: ServiceA -> ServiceB -> ServiceA.
All services in a circular dependency must be lazy singletons.
Unsafe: ServiceB (not lazy)
```

{: .tip }
Если вы столкнулись с ошибкой циклической зависимости, решения:
1. Сделать все сервисы в цикле ленивыми синглтонами (самый простой вариант)
2. Рефакторинг для разрыва цикла (вынести общую зависимость)
3. Использовать setter-инъекцию для отложенной зависимости

{: .note }
Определения на основе фабрик (`register(..., fn() => ...)`) пропускаются при анализе циклов, так как их зависимости нельзя определить статически.

---

## Setter-инъекция

Настройка вызова методов после создания сервиса:

```php
$builder->register(Mailer::class)
    ->call('setTransport', [SmtpTransport::class])
    ->call('setLogger', [FileLogger::class]);
```

Контейнер разрешает аргументы-имена классов из контейнера, а скалярные аргументы передаёт как есть. Несколько вызовов `call()` выполняются по порядку.

### Когда использовать

- **Необязательные зависимости**, которые не должны быть в конструкторе
- **Интеграция с фреймворком**, где сервисы настраиваются после создания
- **Разрыв циклических зависимостей** путём отложенного ребра

---

## Саморегистрация

Контейнер регистрирует себя как `Psr\Container\ContainerInterface`. Его можно запросить через type hint:

```php
use Psr\Container\ContainerInterface;

class ServiceLocator
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function getService(string $id): object
    {
        return $this->container->get($id);
    }
}
```

{: .warning }
Инъекция самого контейнера — это антипаттерн **Service Locator**. Предпочитайте конструкторную инъекцию конкретных зависимостей. Используйте саморегистрацию только когда действительно нужно динамическое разрешение сервисов (загрузчики плагинов, command bus).

---

## Обработка ошибок

Wirebox выбрасывает специфичные, описательные исключения:

| Исключение | Когда возникает |
|:-----------|:----------------|
| `NotFoundException` | Сервис не найден и не может быть автовайрен |
| `AutowireException` | Не удаётся разрешить параметр конструктора (нет type hint, неразрешимый тип) |
| `CircularDependencyException` | Обнаружена небезопасная циклическая зависимость при сборке |
| `ContainerException` | Общие ошибки контейнера (неоднозначные привязки, неверная конфигурация) |

Все исключения реализуют `Psr\Container\ContainerExceptionInterface`.

### Пример: перехват ошибок

```php
use AsceticSoft\Wirebox\Exception\CircularDependencyException;
use AsceticSoft\Wirebox\Exception\ContainerException;
use Psr\Container\NotFoundExceptionInterface;

try {
    $container = $builder->build();
} catch (CircularDependencyException $e) {
    // "Circular dependency detected: A -> B -> A. ..."
    error_log($e->getMessage());
} catch (ContainerException $e) {
    // "Ambiguous auto-binding for PaymentInterface: StripePayment, PayPalPayment"
    error_log($e->getMessage());
}

try {
    $service = $container->get('NonExistentService');
} catch (NotFoundExceptionInterface $e) {
    // Сервис не найден
}
```

{: .tip }
Все исключения содержат подробные сообщения с полным путём зависимости, что упрощает диагностику проблем.
