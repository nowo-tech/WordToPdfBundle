# GitHub CI

Workflow: `.github/workflows/ci.yml`

- Git hygiene (no Cursor co-author trailers) — REQ-GIT-001
- PHP × Symfony matrix (7.4 / 8.0 / 8.1; PHP 8.2–8.5 with exclusions per REQ-SF-002)
- CS Fixer, PHPStan, PHPUnit
- Coverage gate: **100%** PHP lines (`composer coverage-check`)

LibreOffice is **not** required for the default unit suite. Integration tests use `@group libreoffice` and skip when `soffice` is absent.

Scrutinizer: [`.scrutinizer.yml`](../.scrutinizer.yml) (REQ-CI-002).
