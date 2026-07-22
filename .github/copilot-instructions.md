## AI contribution guidelines (Nowo Symfony bundle)

Use this when suggesting code, tests, documentation, or CI changes for this repository.

### Scope

- This is a **Symfony bundle** published as `nowo-tech/word-to-pdf-bundle` on Packagist.
- Respect the **PHP** (`>=8.2 <8.6`) and **Symfony** (`^7.0 || ^8.0`) ranges in `composer.json`.
- Prefer **PHP 8 attributes**. Do not introduce `doctrine/annotations`.
- Conversion uses **LibreOffice Writer** via Symfony Process; always keep **timeouts** (REQ-RUNTIME-001).

### Code

- Follow **PSR-12** and `.php-cs-fixer.dist.php`.
- Keep changes **minimal** and consistent with `src/` and `tests/`.
- Align with `composer cs-check`, `composer phpstan`, and `composer coverage-check` (100% lines).

### Documentation

- User-facing docs are **English** under `docs/`.
- Only `README.md` (+ `CODE_OF_CONDUCT.md`) at repository root.

### Tests

- Add or update tests for new behaviour; keep **100%** PHP line coverage.
- Integration LibreOffice tests may use `@group libreoffice` and skip when `soffice` is absent.
