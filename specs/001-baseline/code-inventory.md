# Code inventory — baseline

Production code under `src/` mapped to baseline requirements.

| Path | Role | Spec |
|------|------|------|
| `WordToPdfBundle.php` | Bundle registration | REQ-SF |
| `DependencyInjection/Configuration.php` | Profiles tree | REQ-CFG-001 |
| `DependencyInjection/WordToPdfExtension.php` | Load services + params | REQ-CFG |
| `Config/ProfileResolver.php` | Profile merge | US-02 |
| `Config/ResolvedConfig.php` | Resolved profile DTO | US-02 |
| `Converter/WordToPdfConverterInterface.php` | Public API | US-01 |
| `Converter/WordToPdfConverter.php` | Convert + validate source | US-01 |
| `Runtime/LibreOfficeBinaryLocator.php` | Find soffice | US-03 |
| `Runtime/RuntimeRequirementsChecker.php` | Assert Writer ready | US-03 |
| `Runtime/LibreOfficeProcessRunner.php` | Process conversion | US-01 |
| `Result/ConvertedPdf.php` | PDF result handle | US-04 |
| `Export/ExporterInterface.php` | Export contract | US-04 |
| `Export/PdfExporter.php` | HTTP / file / Flysystem | US-04 |
| `Command/CheckRuntimeCommand.php` | CLI check | US-03 |
| `EventListener/RuntimeBootCheckListener.php` | Optional boot check | US-03 |
| `Exception/*` | Typed errors | US-03 |

Tests: `tests/Unit/*`, `tests/Integration/LibreOfficeConversionTest.php` (`@group libreoffice`).
