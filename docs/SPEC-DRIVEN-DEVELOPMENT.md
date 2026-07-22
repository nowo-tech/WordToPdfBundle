# Spec-driven development

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`). **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **WordToPdfBundle** guarantees to applications (LibreOffice conversion, profiles, runtime checks). This is spelled out below and in [`USAGE.md`](USAGE.md) / [`CONFIGURATION.md`](CONFIGURATION.md); **PHPUnit** and **PHPStan** enforce it in CI.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); tests and static analysis are the mechanical proof.

## Table of contents

- [User stories](#user-stories)
- [Bundle functional scope](#bundle-functional-scope)
- [Configuration](#configuration)
- [Demos and REQ anchors](#demos-and-req-anchors)
- [Verification](#verification)

## User stories

| ID | Story |
| --- | --- |
| US-01 | **As a** Symfony integrator, **I want** to call `WordToPdfConverterInterface::convert($path)` **so that** I obtain a PDF without wiring LibreOffice myself. |
| US-02 | **As a** integrator, **I want** named YAML profiles **so that** timeouts, filters, and export filenames differ by use case. |
| US-03 | **As an** operator, **I want** `nowo:word-to-pdf:check` **so that** missing LibreOffice Writer fails with clear install hints. |
| US-04 | **As a** integrator, **I want** Symfony binary/stream responses **so that** I can download PDFs from controllers. |
| US-05 | **As an** operator, **I want** the same code under PHP-FPM and FrankenPHP **so that** deploy targets stay interchangeable. |

**Out of scope:** DomPDF/PhpWord PDF writers; Word template mail-merge; Microsoft Graph / Aspose adapters; async Messenger (v1).

## Bundle functional scope

- Input: `.docx` / `.doc` (extension + light mime checks).
- Engine: LibreOffice Writer headless (`soffice --convert-to`).
- Runtime: `RuntimeRequirementsChecker` before every conversion.
- Output: `ConvertedPdf` + `PdfExporter` (stream / binary / file / Flysystem).

## Configuration

Canonical keys: `nowo_word_to_pdf.default_profile` + `nowo_word_to_pdf.profiles` (see [`CONFIGURATION.md`](CONFIGURATION.md)).

## Demos and REQ anchors

| Anchor | Where |
|--------|--------|
| REQ-DEMO-005 | `demo/symfony8/Makefile` `Demo started at:` |
| REQ-DEMO-007 | `update-bundle` / path mount `/var/word-to-pdf-bundle` |
| REQ-MAKE-002 | Root `release-check` → `test-coverage` + `release-check-demos` |

## Verification

```bash
make qa
make release-check
composer coverage-check   # 100% PHP lines
```
