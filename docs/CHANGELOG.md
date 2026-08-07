# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.2.4] - 2026-08-07](#124-2026-08-07)
- [[1.2.3] - 2026-08-04](#123-2026-08-04)
- [[1.2.2] - 2026-07-29](#122-2026-07-29)
- [[1.2.1] - 2026-07-22](#121-2026-07-22)
- [[1.2.0] - 2026-07-22](#120-2026-07-22)
- [[1.1.1] - 2026-07-22](#111-2026-07-22)
- [[1.1.0] - 2026-07-22](#110-2026-07-22)
- [[1.0.0] - 2026-07-22](#100-2026-07-22)

## [Unreleased]

## [1.2.4] - 2026-08-07

### Fixed
- **CI:** restore **100%** line coverage for `LibreOfficeBinaryLocator` (empty candidate skip + empty-path guard).

[1.2.4]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.2.4

## [1.2.3] - 2026-08-04

### Fixed
- **CI:** php-cs-fixer alignment in `LibreOfficeBinaryLocator::findOnPath`.

[1.2.3]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.2.3

## [1.2.2] - 2026-07-29

### Added

- FrankenPHP Friendly Worker Mode banner (REQ-DOCS-017); `make check-open-prs` / `demo-smoke`.
- `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` in PHPUnit and CI (REQ-SF-005).
- Packagist keywords `php` / `frankenphp` and `support` links (REQ-PKG-004).

### Changed

- PHPStan: empty `ignoreErrors` + frankenphp rulesets (REQ-CS-005/006); LibreOffice locator hardened for `non-empty-string` returns.
- Issue templates reference `word-to-pdf-bundle` (REQ-DOCS-014).

## [1.2.1] - 2026-07-22

### Fixed

- Avoid PHP 8.5 deprecation on `finfo_close()` by using the object-oriented `\finfo` API in source mime checks (`WordToPdfConverter`).

### Changed

- PHP-CS-Fixer `fully_qualified_strict_types.import_symbols` enabled (import FQCN in `use` statements).

### Compatibility

- Unchanged from 1.2.0.

## [1.2.0] - 2026-07-22

### Added

- **`convertMany()`** on `WordToPdfConverterInterface` for batch Word → PDF conversion (fail-fast; disposes prior PDFs on error).
- **`PdfNaming`** strategies: `keep()`, `prefix()`, `suffix()`, `surround()`, `fixed()`, `callback()`, plus path ⇒ filename maps that override naming.
- Demo: multipart multi-upload (max 10), flash on invalid extension, download names `{word} [converted].pdf`, ZIP when N&gt;1; uses `convertMany` + `PdfNaming::suffix(' [converted]')`.
- Demo requires `symfony/mime`; form CSRF uses classic session tokens (no Stimulus cookie CSRF).
- Shared env **`PROCESS_TIMEOUT=180`** documented for Nowo process-based bundles (`DEMO-FRANKENPHP.md`).

### Changed

- Profile **`timeout`** default raised from **120** to **180** seconds (**REQ-RUNTIME-001**). Demo wires `timeout: '%env(int:PROCESS_TIMEOUT)%'`.

### Compatibility

- Unchanged: PHP `>= 8.2`, `< 8.6`; Symfony `^7.0 || ^8.0`. Additive API only (no BC break).

## [1.1.1] - 2026-07-22

### Fixed

- Demo `composer.lock` / `composer.json` synced for FrankenPHP **PHP 8.5** (`platform` `>=8.5`, path package ref, `symfony/mime`). Rebuild the demo image if a local container still reports PHP 8.4.
- Rector skip for `RemoveUselessReturnTagRector` / `RemoveDuplicatedReturnSelfDocblockRector` so **REQ-CS-001** `@return` PHPDoc is preserved and `make release-check` stays green.

### Compatibility

- Unchanged from 1.1.0.

## [1.1.0] - 2026-07-22

### Added

- **REQ-RUNTIME-001** — LibreOffice Process **idle timeout**, `stop(0)` on failure/timeout, and orphan `pkill` by `UserInstallation` profile (FrankenPHP-safe).
- Demo stress sample (`public/demo/stress-styles.docx` + generator) and UI inventory of conversion stress features.
- Demo font packages (DejaVu, Liberation, Noto) to avoid tofu boxes (□□□) in PDFs.
- **REQ-DEMO-010** — `FRANKENPHP_MODE` default **`worker`**, `docker/entrypoint.sh`, FrankenPHP **PHP 8.5** (mode only via `.env` / Compose, not Dockerfile `ENV`).
- **REQ-SPECKIT** — Cursor Spec Kit skills, `.specify/` init artifacts, constitution, deep baseline `specs/001-baseline/` (23/23 inventory).
- **REQ-GH** — `copilot-instructions.md`, `pr-lint.yml`, `stale.yml`, weekly CI cron, Codecov upload (skips Dependabot).
- Unit test covering the Process timeout path (`tests/Fixtures/slow-soffice.sh`).

### Changed

- Demo timeout hierarchy documented and wired: Process **180s** &lt; PHP **240s** &lt; Caddy write **250s** (`max_wait_time` **30s**).
- **REQ-DEMO-009** — Packagist DNS comment above `dns:` in demo compose.
- **REQ-DOCS-005 / 012** — TOC on `DEMO-FRANKENPHP.md`; fuller PR template.
- **REQ-CS-001** — English PHPDoc on public APIs (`@param` / `@return`); `no_superfluous_phpdoc_tags` disabled so tags are kept.
- Docs: `CONFIGURATION.md`, `SECURITY.md`, `DEMO-FRANKENPHP.md`, `SPEC-KIT.md`, `SPEC-DRIVEN-DEVELOPMENT.md`, `GITHUB_CI.md`.

### Compatibility

- Unchanged: PHP `>= 8.2`, `< 8.6`; Symfony `^7.0 || ^8.0` (CI: 7.4, 8.0, 8.1).
- Demo image: FrankenPHP **PHP 8.5** Alpine + LibreOffice + fonts + `procps`.

## [1.0.0] - 2026-07-22

First stable release.

### Added

- Symfony bundle for **Microsoft Word (`.docx` / `.doc`) → PDF** conversion via **LibreOffice Writer** (`soffice` headless).
- Named YAML profiles (`default_profile` + `profiles`) with deep merge for `convertWithOptions()`, plus `convertWithInlineProfile()` (no YAML merge).
- Runtime dependency checks: `WordToPdfConverterInterface::assertRuntimeReady()` and CLI `nowo:word-to-pdf:check` (install hints when Writer is missing).
- Optional boot-time check (`check_on_boot` / `boot_failure`) via kernel request listener.
- `PdfExporter` / `ExporterInterface`: stream response, binary response, local file, optional Flysystem.
- Symfony Flex recipe (`nowo_word_to_pdf` under `.symfony/recipe`).
- FrankenPHP demo with LibreOffice (`demo/symfony8`, port 8022) and Twig Inspector.
- Unit and integration tests with **100%** line coverage gate; QA toolchain (CS Fixer, PHPStan, Rector); Scrutinizer + GitHub Actions (PHP 8.2–8.5 × Symfony 7.4 / 8.0 / 8.1).
- REQ-GIT-001 hygiene: commit-msg hook and `check-no-cursor-coauthor` / strip scripts.

### Compatibility

- PHP `>= 8.2`, `< 8.6`
- Symfony `^7.0 || ^8.0` (CI matrix: 7.4, 8.0, 8.1)
- System: **LibreOffice Writer** (`libreoffice-writer` / `soffice`) on the host or container
- Demo image: FrankenPHP **PHP 8.4** (Symfony 8.1)

[Unreleased]: https://github.com/nowo-tech/WordToPdfBundle/compare/v1.2.4...HEAD
[1.2.4]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.2.4
[1.2.3]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.2.3
[1.2.2]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.2.2
[1.2.1]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.2.1
[1.2.0]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.2.0
[1.1.1]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.1.1
[1.1.0]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.0.0
