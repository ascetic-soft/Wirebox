---
title: Переменные окружения
layout: default
nav_order: 5
parent: Русский
---

# Переменные окружения
{: .no_toc }

Встроенная поддержка dotenv с 3-уровневым приоритетом и приведением типов.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Обзор

Wirebox имеет **встроенный** парсер `.env` — внешние пакеты вроде `vlucas/phpdotenv` или `symfony/dotenv` не нужны. Переменные окружения разрешаются с 3-уровневой системой приоритета.

---

## Приоритет разрешения

Переменные разрешаются в порядке приоритета (от высшего к низшему):

| Приоритет | Источник | Описание |
|:----------|:---------|:---------|
| **1** (высший) | `.env.local.php` | PHP-файл с массивом. Генерируется `composer dump-env`. Самый быстрый. |
| **2** | `$_ENV` / `getenv()` | Реальные системные переменные окружения (Docker, CI и т.д.) |
| **3** (низший) | `.env` | Парсится встроенным `DotEnvParser`. Фоллбэк для разработки. |

Все файлы ищутся относительно `projectDir`, переданного в `ContainerBuilder`.

{: .note }
Если переменная определена на нескольких уровнях, побеждает источник с высшим приоритетом. Например, системная переменная переопределяет `.env`, а `.env.local.php` переопределяет всё.

---

## Файл `.env`

Создайте файл `.env` в корне проекта с настройками для разработки:

```env
# Приложение
APP_NAME=Wirebox
APP_DEBUG=true

# База данных
DB_HOST=localhost
DB_PORT=5432
DB_NAME=myapp

# Секреты
SECRET_KEY=dev-secret-key-change-in-production
```

### Поддерживаемый синтаксис

```env
# Комментарии начинаются с #
APP_NAME=Wirebox

# Значения в кавычках (сохраняют пробелы)
GREETING="Hello World"

# Одинарные кавычки (литерал, без интерполяции)
PATTERN='${NOT_INTERPOLATED}'

# Интерполяция переменных (двойные кавычки или без кавычек)
BASE_PATH=/opt
FULL_PATH="${BASE_PATH}/app"
ALSO_WORKS=${BASE_PATH}/app

# Префикс export удаляется
export SECRET_KEY=abc123

# Пустые значения
EMPTY_VALUE=
```

### Правила интерполяции

| Синтаксис | Поведение |
|:----------|:----------|
| `${VAR}` в двойных кавычках | Интерполируется |
| `${VAR}` без кавычек | Интерполируется |
| `${VAR}` в одинарных кавычках | **Литерал** (не интерполируется) |

---

## Использование переменных окружения

### Через атрибут `#[Param]`

Самый простой способ — инъекция прямо в параметры конструктора:

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

Приведение типов происходит автоматически по type hint параметра.

### Через `parameter()` билдера

Определение именованных параметров с env-выражениями:

```php
$builder->parameter('db.host', '%env(DB_HOST)%');
$builder->parameter('db.port', '%env(int:DB_PORT)%');
$builder->parameter('app.debug', '%env(bool:APP_DEBUG)%');
```

Затем получение из контейнера:

```php
$host = $container->getParameter('db.host');
$port = $container->getParameter('db.port');  // int
```

---

## Приведение типов

### В выражениях `parameter()`

Используйте префикс `тип:` внутри `%env(...)%`:

```php
$builder->parameter('port', '%env(int:DB_PORT)%');     // int
$builder->parameter('rate', '%env(float:RATE_LIMIT)%'); // float
$builder->parameter('debug', '%env(bool:APP_DEBUG)%');  // bool
$builder->parameter('host', '%env(DB_HOST)%');          // string (по умолчанию)
```

| Тип | Выражение | Вход | Выход |
|:----|:----------|:-----|:------|
| `string` | `%env(DB_HOST)%` | `"localhost"` | `"localhost"` |
| `int` | `%env(int:DB_PORT)%` | `"5432"` | `5432` |
| `float` | `%env(float:RATE)%` | `"1.5"` | `1.5` |
| `bool` | `%env(bool:DEBUG)%` | `"true"` | `true` |

### Правила приведения к bool

| Значение | Результат |
|:---------|:----------|
| `"true"`, `"1"`, `"yes"`, `"on"` | `true` |
| `"false"`, `"0"`, `"no"`, `"off"`, `""` | `false` |

### Через атрибут `#[Param]`

Приведение типов по PHP type hint:

```php
public function __construct(
    #[Param('DB_PORT')] private int $port,      // приведение к int
    #[Param('RATE')] private float $rate,        // приведение к float
    #[Param('DEBUG')] private bool $debug,       // приведение к bool
    #[Param('HOST')] private string $host,       // string (без приведения)
)
```

---

## Встроенные выражения

Env-выражения можно встраивать в строки:

```php
$builder->parameter('dsn', 'mysql:host=%env(DB_HOST)%;port=%env(DB_PORT)%');
// Результат: "mysql:host=localhost;port=5432"

$builder->parameter('redis.url', 'redis://%env(REDIS_HOST)%:%env(REDIS_PORT)%');
// Результат: "redis://127.0.0.1:6379"
```

{: .note }
Когда env-выражение встроено в строку, результат всегда строка — даже с префиксом приведения типа. Приведение работает только когда выражение — единственное значение параметра.

---

## Продакшен: `.env.local.php`

Для продакшена избегайте парсинга `.env` файлов. Сгенерируйте кешированный PHP-массив:

```bash
# С помощью плагина Symfony для Composer
composer dump-env prod
```

Это создаст `.env.local.php`:

```php
<?php
return [
    'APP_NAME' => 'Wirebox',
    'APP_DEBUG' => 'false',
    'DB_HOST' => 'db.production.internal',
    'DB_PORT' => '5432',
];
```

Поскольку это обычный PHP-массив, накладные расходы на парсинг нулевые — просто подключение файла.

{: .tip }
Файл `.env.local.php` имеет **наивысший приоритет**. Он переопределяет и системные переменные, и `.env`. Это делает деплой предсказуемым — что сдампили, то и получите.

---

## Порядок разрешения переменных

Вот как Wirebox разрешает переменную окружения:

```
Запрос: %env(int:DB_PORT)%
         │
         ▼
   .env.local.php существует?
         │
    ┌────┴────┐
   Да         Нет
    │          │
    ▼          ▼
  Вернуть   $_ENV / getenv()
  значение  содержит DB_PORT?
               │
          ┌────┴────┐
         Да         Нет
          │          │
          ▼          ▼
        Вернуть   Парсить .env
        значение  содержит DB_PORT?
                     │
                ┌────┴────┐
               Да         Нет
                │          │
                ▼          ▼
              Вернуть   Выбросить
              значение  исключение
```

---

## Лучшие практики

1. **Не коммитьте `.env`** с реальными секретами. Используйте `.env.example` как шаблон:
   ```env
   DB_HOST=localhost
   DB_PORT=5432
   SECRET_KEY=change-me
   ```

2. **Используйте `.env.local.php` в продакшене** для нулевых накладных расходов:
   ```bash
   composer dump-env prod
   ```

3. **Используйте системные переменные в Docker/CI**:
   ```dockerfile
   ENV DB_HOST=db.internal
   ENV DB_PORT=5432
   ```

4. **Приводите типы на границе** — используйте префиксы `int:`, `float:`, `bool:` для корректных типов с самого начала.

5. **Держите `.env` минимальным** — только умолчания для разработки. Продакшен-конфигурация должна приходить из окружения или `.env.local.php`.
