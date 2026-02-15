# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.2.1] - 2026-02-15

### Fixed
- Built-in PHP interfaces (`Throwable`, `Stringable`, `Serializable`, `JsonSerializable`, etc.) no longer trigger false-positive ambiguous auto-binding errors when multiple classes implementing them are scanned. Uses `ReflectionClass::isInternal()` to skip all interfaces defined by PHP core and extensions.

### Added
- Test fixtures (`FixturesInternalInterfaces/`) and test `testBuiltInPhpInterfacesSkipAmbiguousBindingCheck` covering the fix.

## [1.2.0] - 2026-02-13

### Added
- **Build-time circular dependency detection.** `build()` and `compile()` now analyse the dependency graph and throw `CircularDependencyException` for unsafe cycles before the container is created. A cycle is safe only when all services in it are lazy singletons; eager services or lazy transients are reported with a detailed hint.
- `#[Eager]` attribute documentation in README — opt out of lazy instantiation per class.
- Default lazy mode documentation in README — `ContainerBuilder` enables lazy proxies by default.
- New "Circular Dependencies" section in README explaining safe/unsafe cycles with examples.
- `CircularDependencyException` now accepts an optional `$hint` parameter for descriptive error messages (backward compatible).
- 11 new tests covering all circular dependency scenarios: eager cycles, lazy singleton (safe), lazy transient, mixed lazy/eager, mixed singleton/transient, factory skip, compile path, default-lazy inheritance, and full runtime E2E verification.

### Changed
- `ContainerBuilder::build()` calls `detectUnsafeCircularDependencies()` after `resolveDefaultLazy()`.
- `ContainerBuilder::compile()` calls `detectUnsafeCircularDependencies()` after `resolveDefaultLazy()`.
- Error Handling table in README updated with more precise descriptions.

## [1.1.1] - 2026-02-12

### Added
- **Lazy proxy support.** Deferred instantiation via PHP 8.4 native `ReflectionClass::newLazyProxy()`. A lightweight proxy is returned immediately; the real instance is created only on first property/method access.
- `#[Lazy]` attribute — mark a class for lazy instantiation.
- `#[Eager]` attribute — opt out of lazy mode when the container's `defaultLazy` is enabled.
- `Definition::lazy()` / `Definition::eager()` / `Definition::hasExplicitLazy()` fluent API.
- `ContainerBuilder::defaultLazy()` — toggle default lazy mode (enabled by default in the builder).
- Lazy proxies fully supported in the compiled container (`ContainerCompiler`).
- **Autoconfiguration.** Automatically tag and configure services by interface or attribute (Symfony-style).
- `#[AutoconfigureTag]` attribute — place on an interface or custom attribute to auto-tag all implementing/decorated classes.
- `AutoconfigureRule` class with fluent API (`tag()`, `singleton()`, `transient()`, `lazy()`, `eager()`).
- `ContainerBuilder::registerForAutoconfiguration()` — programmatic autoconfiguration rules.
- Autoconfigured interfaces are excluded from ambiguous auto-binding checks (multiple implementations are expected).
- Test fixtures: `LazyService`, `LazyServiceWithDeps`, `LazyTransientService`, and full `FixturesAutoconfigure/` directory with CQRS-style handlers, event listeners, and scheduled tasks.
- Comprehensive test coverage for lazy proxies (container, builder, compiler) and autoconfiguration.

### Changed
- `Container::autowireClass()` now reads `#[Lazy]`, `#[Eager]`, and `#[Transient]` attributes and creates proxies accordingly.
- `ContainerBuilder::scan()` reads `#[Lazy]` and `#[Eager]` attributes and applies autoconfiguration rules.
- `ContainerBuilder::build()` / `compile()` apply `resolveDefaultLazy()` to set the lazy flag on definitions without an explicit setting.

## [1.1.0] - 2025-02-12

### Added
- Ambiguous auto-binding detection: if two or more classes implement the same interface, `build()` throws `ContainerException` unless resolved with an explicit `bind()` call.
- New `testBuildThrowsOnAmbiguousAutoBinding` and `testExplicitBindResolvesAmbiguousAutoBinding` tests covering the ambiguous auto-binding scenario.
- `testScanAutoBindsInterfaceWithSingleImplementation` test verifying auto-binding works correctly for interfaces with a single implementation.
- Ambiguous auto-binding detection (`tests/FixturesAmbiguous/`) with `PaymentInterface`, `StripePayment`, and `PayPalPayment` for ambiguous binding tests.
- PHPDoc for `scan()` method now documents auto-binding and ambiguity behavior.
- README section "Interface Binding" expanded with ambiguity handling explanation and example.

### Changed
- `ContainerBuilder::scan()` now tracks ambiguous interface bindings instead of silently keeping the first implementation found.
- `ContainerBuilder::bind()` now clears the ambiguous flag for the given interface.
- `ContainerBuilder::build()` validates that no unresolved ambiguous bindings remain before creating the container.
- Exception messages across the codebase (`Autowirer`, `Container`, `CompiledContainer`, `EnvResolver`, `ClassScanner`) refactored from string interpolation to `sprintf()` for consistency and static analysis compliance.
