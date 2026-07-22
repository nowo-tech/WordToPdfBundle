# Security — WordToPdfBundle

This document describes the **attack surface**, **threats**, and **controls** for `nowo-tech/word-to-pdf-bundle`. It is written in English per project standards.

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threats and mitigations](#threats-and-mitigations)
- [Logging and secrets](#logging-and-secrets)
- [Cryptography](#cryptography)
- [PHP runtime notes (FPM & FrankenPHP)](#php-runtime-notes-fpm--frankenphp)
- [Reporting](#reporting)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope

The bundle converts **Microsoft Word** (`.docx` / `.doc`) files to **PDF** by invoking **LibreOffice Writer** (`soffice`) via Symfony Process. It may:

- Read Word files from paths chosen by the application (or uploaded files handled by the app).
- Write temporary workspaces and PDF output under a configured temp directory.
- Optionally upload PDF bytes via Flysystem when the application injects an adapter.

It does **not** expose HTTP routes by itself; the host application controls authorization, upload validation, and download responses. The optional demo app is excluded from the Packagist archive.

## Attack surface

| Input | Source | Notes |
|-------|--------|-------|
| Source Word path | Application | Must point to an intended file; avoid user-controlled absolute paths without validation. |
| Profile options | YAML / ad-hoc arrays | Timeout, max size, binary path, filter string. |
| LibreOffice binary | System package | Must be a trusted install (`libreoffice-writer` / equivalent). |
| Temp directory | Config / OS | Must be writable and not world-shared if multi-tenant. |

## Threats and mitigations

### Missing or malicious converter binary

- **Risk**: Calling an unexpected executable if `binary_path` is attacker-controlled.
- **Mitigation**: Keep `binary_path` under operator control; default auto-detect uses well-known paths. Runtime check fails closed when the binary is missing.

### Process / resource abuse

- **Risk**: Huge Word files or hanging LibreOffice processes exhaust CPU/disk.
- **Mitigation**: `max_source_bytes` (default 50 MiB), `timeout` per profile, isolated temp workspace deleted after conversion.

### Path traversal / sensitive file read

- **Risk**: Converting arbitrary local paths (e.g. `/etc/passwd` renamed) if the app forwards user paths.
- **Mitigation**: Application must validate uploads (extension, mime, size) and never pass raw user path strings. Demo only accepts uploaded files.

### Shell injection

- **Risk**: Crafted filenames leading to shell metacharacters.
- **Mitigation**: Symfony Process is invoked with an **argument array** (no shell). Basenames are sanitized before copy into the workspace.

### Document macros / malicious Office content

- **Risk**: Macro-enabled documents.
- **Mitigation**: LibreOffice is started headless without enabling macros; still treat untrusted uploads carefully (virus scan at app boundary).

### Dependency vulnerabilities

- **Mitigation**: Run `composer audit` before releases; keep Symfony Process / Flysystem updated.

## Logging and secrets

Do not log full document contents or temp paths that reveal customer data. Avoid logging credentials if Flysystem adapters are configured.

## Cryptography

Not applicable; no custom cryptography in this bundle.

## PHP runtime notes (FPM & FrankenPHP)

- `proc_open` must not be disabled (`disable_functions`).
- The same Process-based conversion works under PHP-FPM and FrankenPHP.

## Reporting

See the repository `.github/SECURITY.md` for coordinated disclosure contacts.

## Release security checklist (12.4.1)

Before each tagged release, maintainers confirm (tick in the release PR or tag notes):

| Item | Confirm |
|------|--------|
| `docs/SECURITY.md` and `.github/SECURITY.md` reviewed | ☐ |
| `.env` / secrets not committed (`.gitignore` baseline) | ☐ |
| No secrets in recipes or sample configs | ☐ |
| Inputs validated at application boundary where untrusted | ☐ |
| `composer audit` clean or exceptions documented | ☐ |
| No sensitive data in logs | ☐ |
| Permissions / exposure of generated PDF/temp files acceptable | ☐ |
| Resource limits (`timeout`, `max_source_bytes`) considered | ☐ |
| LibreOffice Writer present on deploy targets; `nowo:word-to-pdf:check` documented | ☐ |
