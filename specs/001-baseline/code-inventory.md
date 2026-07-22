# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/word-to-pdf-bundle`  
**Last audited**: 2026-07-22

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and demo trees are out of Packagist scope unless promoted in the spec.

## Coverage summary

| Category | Count |
| --- | --- |
| PHP classes / interfaces | 21 |
| Symfony YAML under `Resources/config` | 2 |
| **Total inventory rows** | **23** |

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `WordToPdfBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `Command/CheckRuntimeCommand.php` | CLI check | FR-CLI-001, US-03 |
| `Config/ProfileResolver.php` | Profile merge | FR-CONVERT-002, US-02 |
| `Config/ResolvedConfig.php` | Resolved profile DTO | FR-CONVERT-002, US-02 |
| `Converter/WordToPdfConverterInterface.php` | Public API | FR-CONVERT-001, US-01 |
| `Converter/WordToPdfConverter.php` | Convert + validate | FR-CONVERT-001, US-01, US-05 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/WordToPdfExtension.php` | DI extension | FR-CFG-002 |
| `EventListener/RuntimeBootCheckListener.php` | Optional boot check | FR-BOOT-001, US-03 |
| `Exception/WordToPdfExceptionInterface.php` | Exception marker | FR-ERR-001 |
| `Exception/ConversionFailedException.php` | Conversion errors | FR-ERR-001 |
| `Exception/ExportException.php` | Export errors | FR-ERR-001, US-04 |
| `Exception/InvalidProfileException.php` | Profile errors | FR-ERR-001, US-02 |
| `Exception/MissingDependencyException.php` | Missing Writer | FR-ERR-001, US-03 |
| `Exception/UnsupportedFormatException.php` | Bad extension | FR-ERR-001, US-01 |
| `Export/ExporterInterface.php` | Export contract | FR-EXPORT-001, US-04 |
| `Export/PdfExporter.php` | HTTP / file / Flysystem | FR-EXPORT-001, US-04 |
| `Result/ConvertedPdf.php` | PDF result handle | FR-CONVERT-001, US-04 |
| `Runtime/LibreOfficeBinaryLocator.php` | Find soffice | FR-RUNTIME-001, US-03 |
| `Runtime/RuntimeRequirementsChecker.php` | Assert Writer ready | FR-RUNTIME-001, US-03 |
| `Runtime/LibreOfficeProcessRunner.php` | Process conversion + timeouts | FR-RUNTIME-002, US-01, US-05 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `services.yaml` | Service wiring | FR-DI-001 |
| `nowo_word_to_pdf.yaml` | Sample package config | FR-DI-001, FR-CFG-001 |

## Out of inventory scope

| Path | Reason |
| --- | --- |
| `tests/**` | Test-only |
| `demo/**` | Illustrative; excluded from Packagist archive |
| `.cursor/**`, `.specify/**`, `specs/**` | Tooling / specs |

Tests: `tests/Unit/*`, `tests/Integration/LibreOfficeConversionTest.php` (`@group libreoffice`).
