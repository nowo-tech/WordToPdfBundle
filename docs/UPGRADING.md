# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.2.5](#to-125)
- [To 1.2.4](#to-124)
- [To 1.2.3](#to-123)
- [To 1.2.2](#to-122)
- [To 1.2.1](#to-121)
- [To 1.2.0](#to-120)
- [To 1.1.1](#to-111)
- [To 1.1.0](#to-110)
- [To 1.0.0 (initial release)](#to-100-initial-release)
- [Version 1.x](#version-1x)

## Unreleased

## To 1.2.5

From **1.2.4** — No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/word-to-pdf-bundle
```

_Placeholder for the next release._

## To 1.2.4

No breaking public API changes. Safe to upgrade with:

```bash
composer update nowo-tech/word-to-pdf-bundle
```

### Behavioral notes (non-breaking)

- **CI / tests:** additional unit coverage for empty LibreOffice candidate paths and the empty-path guard in `LibreOfficeBinaryLocator` (restores the 100% line coverage gate).

### Breaking changes

None.

## To 1.2.3

No breaking public API changes. Patch release for php-cs-fixer alignment only.

## To 1.2.2

No breaking public API changes. Safe to upgrade with:

```bash
composer update nowo-tech/word-to-pdf-bundle
```

### Behavioral notes (non-breaking)

- **CI / tests:** `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- **Runtime:** `LibreOfficeBinaryLocator` accepts optional `$pathEnv` for tests (no `putenv`); return types hardened for PHPStan `non-empty-string`.

### Breaking changes

None.

## To 1.2.1

No breaking public API changes. Safe to upgrade with:

```bash
composer update nowo-tech/word-to-pdf-bundle
```

### Behavioral notes (non-breaking)

- Source mime probing no longer calls `finfo_close()` (deprecated on PHP 8.5); uses `new \finfo(...)` instead. Apps that copy the old procedural pattern should apply the same change.

### Breaking changes

None.

## To 1.2.0

No breaking public API changes. Safe to upgrade with:

```bash
composer update nowo-tech/word-to-pdf-bundle
```

### Behavioral notes (non-breaking)

- Default profile **`timeout`** is now **180** seconds (was 120). Apps that relied on the previous default without setting `timeout` explicitly get a longer conversion window.
- Demos standardize on shared env **`PROCESS_TIMEOUT=180`** → `timeout: '%env(int:PROCESS_TIMEOUT)%'`. Other Nowo bundles that spawn Symfony Process should reuse the same variable name and default — see [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#shared-process_timeout-all-nowo-process-based-bundles).
- New optional API: `convertMany()` + `PdfNaming` for batch conversion and output naming (keep / prefix / suffix / surround / fixed / callback, or path ⇒ filename map). Existing single-file methods are unchanged. See [USAGE.md](USAGE.md#convert-many-batch--pdf-naming).

### Breaking changes

None.

## To 1.1.1

No package API changes. Safe to upgrade with:

```bash
composer update nowo-tech/word-to-pdf-bundle
```

### Demo-only

- If `composer update` in `demo/symfony8` fails with “requires php >=8.5 but your php version (8.4.x)”, rebuild the image (`make -C demo/symfony8 build`) so it matches FrankenPHP **PHP 8.5**. The demo lockfile platform is now `>=8.5`.

### Breaking changes

None.

## To 1.1.0

No breaking public API changes. Safe to upgrade with:

```bash
composer update nowo-tech/word-to-pdf-bundle
```

### Behavioral notes (non-breaking)

- Profile **`timeout`** is now applied as Symfony Process **wall-clock and idle timeout**. On timeout or process failure the runner calls `Process::stop(0)` and attempts to reap LibreOffice children matching the conversion `UserInstallation` workspace (`pkill`). Ensure `pkill` / `procps` exists in conversion images if you rely on orphan cleanup (the demo Dockerfile already installs `procps`).
- Keep PHP `max_execution_time` and reverse-proxy write deadlines **above** your profile `timeout` (see [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#timeouts-avoid-stuck-frankenphp-workers--orphaned-soffice) and [CONFIGURATION.md](CONFIGURATION.md)).

### Demo-only

- Default `FRANKENPHP_MODE` is **`worker`** (was often `classic` locally). Set `classic` in `demo/symfony8/.env` and recreate containers if you need per-request PHP. Image is FrankenPHP **PHP 8.5** Alpine.

### Breaking changes

None.

## To 1.0.0 (initial release)

This is the first stable release. Install or require the package:

```bash
composer require nowo-tech/word-to-pdf-bundle:^1.0
```

### Requirements

- PHP `>= 8.2`, `< 8.6`
- Symfony `^7.0 || ^8.0` (CI covers 7.4, 8.0, 8.1)
- **LibreOffice Writer** installed on every environment that converts documents (`apt install libreoffice-writer`, `apk add libreoffice`, etc.)
- `proc_open` not listed in `disable_functions` (Symfony Process)

### Enable and configure

Symfony Flex registers the bundle and copies `config/packages/nowo_word_to_pdf.yaml`. Manual setup:

```php
// config/bundles.php
Nowo\WordToPdfBundle\WordToPdfBundle::class => ['all' => true],
```

```yaml
# config/packages/nowo_word_to_pdf.yaml
nowo_word_to_pdf:
    default_profile: default
    profiles:
        default:
            timeout: 180
            export:
                filename: document.pdf
```

Verify the runtime:

```bash
php bin/console nowo:word-to-pdf:check
```

### Public API

- `Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface` — `convert()`, `convertWithProfile()`, `convertWithOptions()`, `convertWithInlineProfile()`, `assertRuntimeReady()`
- `Nowo\WordToPdfBundle\Export\ExporterInterface` — stream / binary / file / Flysystem helpers

See [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).

### Integrator notes

- Config root key is **`nowo_word_to_pdf`** (not a short alias).
- Dispose converted PDFs when finished (`ConvertedPdf::dispose()`) so temp workspaces are cleaned up.
- DomPDF / PhpWord PDF writers are **not** used; layout fidelity comes from LibreOffice Writer export.
- Related packages (optional): [WordTemplateBundle](https://github.com/nowo-tech/WordTemplateBundle), [HtmlToWordBundle](https://github.com/nowo-tech/HtmlToWordBundle).

### Breaking changes

None (initial release).

## Version 1.x

Future breaking changes will be listed here with migration steps.
