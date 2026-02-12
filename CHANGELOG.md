# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
