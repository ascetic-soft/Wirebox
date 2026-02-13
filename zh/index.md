---
title: 首页
layout: default
nav_order: 1
parent: 中文
permalink: /zh/
---

# Wirebox

{: .fs-9 }

轻量级 PHP 8.4 依赖注入容器，支持自动装配、属性配置与编译容器。
{: .fs-6 .fw-300 }

[![CI](https://github.com/ascetic-soft/Wirebox/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/Wirebox/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/Wirebox/graph/badge.svg?token=yotFHWiMtP)](https://codecov.io/gh/ascetic-soft/Wirebox)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/wirebox/php)](https://packagist.org/packages/ascetic-soft/wirebox)
[![License](https://img.shields.io/packagist/l/ascetic-soft/wirebox)](https://packagist.org/packages/ascetic-soft/wirebox)

[快速开始]({{ '/zh/getting-started.html' | relative_url }}){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[English]({{ '/' | relative_url }}){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## 什么是 Wirebox？

Wirebox 是一个面向 PHP 8.4+ 的现代化**依赖注入容器**，无需配置。它利用 PHP 最新特性——原生属性、惰性对象和反射——提供强大而简洁的依赖注入体验。

### 核心特性

- **零配置** — 指定一个目录，所有具体类自动注册
- **PSR-11 兼容** — 标准 `ContainerInterface` 实现
- **PHP 8.4 属性** — 声明式服务配置：`#[Singleton]`、`#[Inject]`、`#[Lazy]` 等
- **自动装配** — 通过类型提示自动解析构造函数依赖
- **编译容器** — 生成零反射的 PHP 类，用于生产环境
- **惰性代理** — 通过 PHP 8.4 原生惰性对象实现延迟实例化
- **内置 dotenv** — 无外部依赖的环境变量支持
- **自动配置** — 按接口或属性自动标记服务（Symfony 风格）

---

## 快速示例

```php
use AsceticSoft\Wirebox\ContainerBuilder;

$builder = new ContainerBuilder(projectDir: __DIR__);
$builder->scan(__DIR__ . '/src');
$container = $builder->build();

$service = $container->get(App\UserService::class);
```

三行代码即可创建一个功能完整的自动装配 DI 容器。无需 XML、YAML 或样板代码。

---

## 为什么选择 Wirebox？

| 特性 | Wirebox | 其他容器 |
|:-----|:--------|:---------|
| PHP 8.4 原生惰性对象 | 是 | 代理生成 |
| 零配置目录扫描 | 是 | 手动注册 |
| 内置 `.env` 支持 | 是 | 外部包 |
| 编译容器 | 是 | 部分支持 |
| 自动配置 | 是 | 部分支持 |
| 最少依赖 | 仅 `psr/container` | 通常较多 |
| PHPStan Level 9 | 是 | 不一定 |

---

## 环境要求

- **PHP** >= 8.4
- **psr/container** ^2.0

## 安装

```bash
composer require ascetic-soft/wirebox
```

---

## 文档

<div class="grid-container">
  <div class="grid-item">
    <h3><a href="{{ '/zh/getting-started.html' | relative_url }}">快速开始</a></h3>
    <p>安装、创建首个容器，5 分钟上手。</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/zh/configuration.html' | relative_url }}">配置</a></h3>
    <p>目录扫描、绑定、工厂和 Fluent API。</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/zh/attributes.html' | relative_url }}">属性</a></h3>
    <p>所有 PHP 属性：Singleton、Transient、Inject、Param、Tag 等。</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/zh/environment.html' | relative_url }}">环境变量</a></h3>
    <p>内置 dotenv、三级优先级、类型转换。</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/zh/compiled-container.html' | relative_url }}">编译容器</a></h3>
    <p>零反射的生产环境容器生成。</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/zh/advanced.html' | relative_url }}">高级特性</a></h3>
    <p>自动配置、标签服务、惰性代理、循环依赖。</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/zh/api-reference.html' | relative_url }}">API 参考</a></h3>
    <p>所有公共类和方法的完整参考。</p>
  </div>
</div>
