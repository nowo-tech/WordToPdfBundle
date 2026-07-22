# Feature Specification: WordToPdfBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-22  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md), [`docs/DEMO-FRANKENPHP.md`](../../docs/DEMO-FRANKENPHP.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/word-to-pdf-bundle`  
**Configuration root**: `nowo_word_to_pdf`

Symfony bundle that converts Microsoft Word (`.docx` / `.doc`) to PDF via **LibreOffice Writer** (`soffice` headless), with named YAML profiles, runtime dependency checks, Symfony exporters, and FrankenPHP-safe process timeouts.

Production source count: every PHP class under `src/` plus `src/Resources/config/*.yaml` (see inventory).

---

## User Scenarios & Testing

### User Story 1 — Convert Word to PDF (Priority: P1)

**US-01** — As a Symfony integrator, I call `WordToPdfConverterInterface::convert($path)` and obtain a temporary PDF without wiring LibreOffice myself.

**Acceptance Scenarios**:

1. **Given** a readable `.docx` and LibreOffice Writer installed, **When** `convert()` runs, **Then** a `ConvertedPdf` whose bytes start with `%PDF` is returned.
2. **Given** an unsupported extension, **When** convert is called, **Then** `UnsupportedFormatException` is thrown.
3. **Given** conversion succeeds, **When** the caller finishes, **Then** `dispose()` removes temporary workspace artifacts.

### User Story 2 — Named profiles (Priority: P1)

**US-02** — As an integrator, I configure `default_profile` + `profiles` so timeouts, filters, and export filenames differ by use case.

**Acceptance Scenarios**:

1. **Given** YAML profiles `default` and `batch`, **When** `convertWithProfile($path, 'batch')` runs, **Then** the batch timeout/filter apply.
2. **Given** `convertWithOptions()`, **When** ad-hoc options are passed, **Then** they deep-merge over the named profile.
3. **Given** `convertWithInlineProfile()`, **When** a full profile array is passed, **Then** YAML merge is skipped.
4. **Given** `default_profile` missing from `profiles`, **When** the container compiles, **Then** configuration validation fails.

### User Story 3 — Runtime dependency check (Priority: P1)

**US-03** — As an operator, I run `nowo:word-to-pdf:check` so missing LibreOffice fails with clear install hints.

**Acceptance Scenarios**:

1. **Given** Writer is missing, **When** `assertRuntimeReady()` / CLI check runs, **Then** `MissingDependencyException` / exit `1` with `libreoffice-writer` hints.
2. **Given** Writer is present, **When** check runs, **Then** exit `0` and diagnose reports `ok=true`.
3. **Given** `check_on_boot: true` and `boot_failure: exception`, **When** a request hits, **Then** missing Writer aborts the request.

### User Story 4 — Export responses (Priority: P2)

**US-04** — As an integrator, I use `ExporterInterface` for stream/binary/file/Flysystem PDF delivery.

**Acceptance Scenarios**:

1. **Given** a `ConvertedPdf`, **When** `toBinaryResponse()` is called, **Then** Content-Type is `application/pdf`.
2. **Given** Flysystem is not injected, **When** `toFlysystem()` is called, **Then** `ExportException` is thrown.

### User Story 5 — FrankenPHP / Process timeouts (Priority: P1)

**US-05** — As an operator, conversion uses Symfony Process with timeouts so FrankenPHP workers are not left with orphaned `soffice` children.

**Acceptance Scenarios**:

1. **Given** profile `timeout: N`, **When** LibreOffice exceeds N seconds, **Then** `ConversionFailedException` (timed out) is thrown and the process is stopped.
2. **Given** a hanging binary, **When** idle timeout elapses, **Then** the same failure path runs (no endless worker block).

---

## Functional Requirements

| ID | Requirement |
|----|-------------|
| FR-BUNDLE-001 | Bundle registers DI extension alias `nowo_word_to_pdf`. |
| FR-CFG-001 | Config tree exposes `engine`, `default_profile`, `profiles.*` (timeout, binary_path, filter, export, boot check, …). |
| FR-CFG-002 | Extension loads `services.yaml` and publishes profile parameters. |
| FR-CONVERT-001 | Converter validates extension/mime, asserts runtime, runs LibreOffice, returns `ConvertedPdf`. |
| FR-CONVERT-002 | ProfileResolver merges default → named → ad-hoc; `resolveInline` skips YAML. |
| FR-RUNTIME-001 | Binary locator finds `soffice`/`libreoffice`; checker asserts readiness / min version. |
| FR-RUNTIME-002 | ProcessRunner applies timeout + idle timeout; force-stops and reaps orphans on failure (**REQ-RUNTIME-001**). |
| FR-EXPORT-001 | PdfExporter implements stream/binary/file/Flysystem export. |
| FR-CLI-001 | `nowo:word-to-pdf:check` prints diagnose and exit codes. |
| FR-BOOT-001 | Optional request listener boot-checks LibreOffice. |
| FR-ERR-001 | Typed exceptions implement `WordToPdfExceptionInterface`. |
| FR-DI-001 | `services.yaml` + sample `nowo_word_to_pdf.yaml` ship under Resources. |

## Success Criteria

| ID | Criterion |
|----|-----------|
| SC-01 | `composer coverage-check` reports 100% PHP lines. |
| SC-02 | `make release-check` passes (including demo HTTP smoke when demos present). |
| SC-03 | FrankenPHP demo documents timeout hierarchy and `FRANKENPHP_MODE`. |

## Out of scope

- DomPDF / PhpWord PDF writers; Word template mail-merge; Microsoft Graph / Aspose; async Messenger (v1).
