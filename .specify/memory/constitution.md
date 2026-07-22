# WordToPdfBundle Constitution

## Core Principles

### I. Documented integrator contract

Product behavior lives in `specs/001-baseline/spec.md`, `docs/SPEC-DRIVEN-DEVELOPMENT.md`, and integrator docs (`USAGE.md`, `CONFIGURATION.md`, `INSTALLATION.md`). Demos are illustrative unless promoted in the spec.

### II. Spec-first, test-proven

PHPUnit and PHPStan are the mechanical proof. Behavioral changes require tests. Line coverage floor is **100%** (`composer coverage-check`).

### III. 100% code inventory traceability

Every production file under `src/` must appear in `specs/001-baseline/code-inventory.md` with a stable `FR-*` requirement id. New files require spec updates in the same PR.

### IV. Cursor + Spec Kit

GitHub Spec Kit is initialized with **Cursor Agent** (`cursor-agent`). Skills live in `.cursor/skills/speckit-*` and coexist with `.cursor/rules/` and `.cursor/mcp.json`.

### V. LibreOffice + FrankenPHP safety

Conversion uses Symfony Process with hard **timeout** and **idle timeout**. On expiry the runner force-stops the process and reaps orphaned LibreOffice children so FrankenPHP workers are not left with open `soffice` processes (**REQ-RUNTIME-001**).

### VI. Symfony compatibility

Follow declared PHP/Symfony ranges in `composer.json` and README badges (PHP `>=8.2 <8.6`, Symfony `^7.0 || ^8.0`).

## Security Requirements

- Never shell-interpolate user-controlled paths into LibreOffice; use Process argument arrays.
- Cap source size (`max_source_bytes`) and conversion `timeout`.
- Dispose temporary PDFs (`ConvertedPdf::dispose()`).
- Do not log document contents or customer temp paths.

## Quality Gates

- `composer qa` / `make release-check` before merge and release.
- PHP line coverage floor enforced by project scripts (see README).
- `make check-no-cursor-coauthor` before push (REQ-GIT-001).

## Governance

This constitution guides Spec Kit workflows (`/speckit-*` skills). Amendments require updating this file, the baseline spec if principles affect behavior, and a note in `docs/CHANGELOG.md` when consumer-visible.

**Version**: 1.0.0 | **Ratified**: 2026-07-22 | **Last Amended**: 2026-07-22
