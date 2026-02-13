---
title: Главная
layout: default
nav_order: 1
parent: Русский
permalink: /ru/
---

# Wirebox

{: .fs-9 }

Легковесный DI-контейнер для PHP 8.4 с автовайрингом, атрибутами и компилируемым контейнером.
{: .fs-6 .fw-300 }

[![CI](https://github.com/ascetic-soft/Wirebox/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/Wirebox/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/Wirebox/graph/badge.svg?token=yotFHWiMtP)](https://codecov.io/gh/ascetic-soft/Wirebox)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/wirebox/php)](https://packagist.org/packages/ascetic-soft/wirebox)
[![License](https://img.shields.io/packagist/l/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)

[Быстрый старт]({{ '/ru/getting-started.html' | relative_url }}){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[English]({{ '/' | relative_url }}){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## Что такое Wirebox?

Wirebox — это современный **контейнер внедрения зависимостей** (DI-контейнер) для PHP 8.4+, не требующий конфигурации. Он использует новейшие возможности PHP — нативные атрибуты, ленивые объекты и рефлексию — для удобной и мощной работы с зависимостями.

### Ключевые особенности

- **Нулевая конфигурация** — укажите директорию, все конкретные классы зарегистрируются автоматически
- **PSR-11 совместимость** — стандартная реализация `ContainerInterface`
- **PHP 8.4 атрибуты** — декларативная настройка сервисов: `#[Singleton]`, `#[Inject]`, `#[Lazy]` и другие
- **Автовайринг** — автоматическое разрешение зависимостей конструктора по type hints
- **Компилируемый контейнер** — генерация PHP-класса без рефлексии для продакшена
- **Ленивые прокси** — отложенное создание через нативные lazy objects PHP 8.4
- **Встроенный dotenv** — поддержка `.env` без внешних зависимостей
- **Автоконфигурация** — автоматическая маркировка тегами по интерфейсу или атрибуту (в стиле Symfony)

---

## Быстрый пример

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

$service = $container->get(App\UserService::class);
```

Три строки — и DI-контейнер с автовайрингом готов. Никакого XML, YAML или шаблонного кода.

---

## Почему Wirebox?

| Возможность | Wirebox | Другие контейнеры |
|:------------|:--------|:------------------|
| Нативные lazy objects PHP 8.4 | Да | Генерация прокси |
| Сканирование без конфигурации | Да | Ручная регистрация |
| Встроенная поддержка `.env` | Да | Внешние пакеты |
| Компилируемый контейнер | Да | Не все |
| Автоконфигурация | Да | Не все |
| Минимум зависимостей | Только `psr/container` | Часто много |
| PHPStan Level 9 | Да | По-разному |

---

## Требования

- **PHP** >= 8.4
- **psr/container** ^2.0

## Установка

```bash
composer require ascetic-soft/wirebox
```

---

## Документация

<div class="grid-container">
  <div class="grid-item">
    <h3><a href="{{ '/ru/getting-started.html' | relative_url }}">Быстрый старт</a></h3>
    <p>Установка, первый контейнер и базовое использование за 5 минут.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/configuration.html' | relative_url }}">Конфигурация</a></h3>
    <p>Сканирование директорий, привязки, фабрики и fluent API.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/attributes.html' | relative_url }}">Атрибуты</a></h3>
    <p>Все PHP-атрибуты: Singleton, Transient, Inject, Param, Tag и другие.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/environment.html' | relative_url }}">Переменные окружения</a></h3>
    <p>Встроенный dotenv, 3-уровневый приоритет, приведение типов.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/compiled-container.html' | relative_url }}">Компилируемый контейнер</a></h3>
    <p>Генерация контейнера без рефлексии для продакшена.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/advanced.html' | relative_url }}">Продвинутые возможности</a></h3>
    <p>Автоконфигурация, теги, ленивые прокси, циклические зависимости.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/api-reference.html' | relative_url }}">Справочник API</a></h3>
    <p>Полный справочник по всем публичным классам и методам.</p>
  </div>
</div>
