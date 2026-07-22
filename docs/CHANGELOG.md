# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/nowo-tech/WordToPdfBundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nowo-tech/WordToPdfBundle/releases/tag/v1.0.0
