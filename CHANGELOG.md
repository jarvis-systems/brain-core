# Changelog

All notable changes to jarvis-brain/core will be documented in this file.

## [Unreleased]

## [v0.0.1] — 2026-02-21

Initial enterprise-hardened release. 74 tests, 214 assertions, PHPStan level 0.

### Added
- Test suite: 12 test files, 74 tests, 214 assertions (100% pass)
- Proof Pack v1: BuilderDeterminismTest (5), MergerInvariantsTest (4), CompilationOutputTest (13)
- Proof Pack v2: CompileIdempotencyTest (4), NodeIntegrityTest (8)
- Proof Pack v3: RuntimeTest (7), ToolFormatTest (8), VarExporterDegradationTest (8)
- PHPStan level 0 with 4 documented suppressions
- `logDegradation()` helper in CompileStandardsTrait for VarExporter observability
- `BRAIN_COMPILE_DEBUG` env gate for degradation logging
- McpSchemaValidator with 3 validation modes
- YAML front matter iron rules for documentation

### Changed
- All 9 VarExporter catch blocks: observable `logDegradation()` / `error_log`
- `CompileStandartsTrait` renamed to `CompileStandardsTrait`
- `self::callJson()` LSB fixed to `static::callJson()` in McpSchemaTrait
- Include refinery: VectorTask dedup (-19 compiled lines), dead import removed
- `declare(strict_types=1)` enforced in 167/167 PHP files

### Fixed
- Merger stale-index bug: `array_splice` index rebuilt after splice
- MergerTest: protected `handle()` invocation via Reflection
- TomlBuilderTest: stale `.build()` chain removed
- XmlBuilder: `escape()` → `raw()`, added `escapeXml()` for proper XML escaping
- `dump()` in ConvertCommand replaced with `fwrite(STDERR, ...)`
- `HelloScript.php` dead scaffold removed
- Debug `dd()` blocks removed from XmlBuilder
