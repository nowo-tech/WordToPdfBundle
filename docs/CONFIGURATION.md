# Configuration

Root key: `nowo_word_to_pdf`.

## Table of contents

- [Profiles (REQ-CFG)](#profiles-req-cfg)
- [Boot check](#boot-check)
- [Flysystem export](#flysystem-export)
- [Deprecated keys](#deprecated-keys)
- [Sample file](#sample-file)

## Profiles (REQ-CFG)

```yaml
nowo_word_to_pdf:
    engine: libreoffice
    default_profile: default
    profiles:
        default:
            binary_path: null          # null = auto-detect soffice/libreoffice
            temp_dir: null             # null = sys_get_temp_dir()
            timeout: 180               # seconds (bundle default; prefer %env(int:PROCESS_TIMEOUT)% — shared Nowo default 180)
            max_source_bytes: 52428800 # 50 MiB
            check_on_boot: false
            boot_failure: exception    # exception|warning
            min_version: null          # e.g. "24.2"
            filter: pdf:writer_pdf_Export
            export:
                filename: document.pdf
                flysystem_adapter: null   # service id of a League\Flysystem\FilesystemOperator (see below)
        batch:
            timeout: 300
            export:
                filename: batch.pdf
```

- `default_profile` **must** exist as a key under `profiles`.
- Merge order for `convertWithOptions()`: default profile → named profile → ad-hoc options (deepest wins).
- `convertWithInlineProfile()` uses only the provided array (no YAML merge).
- **`timeout`** (seconds) is applied as Symfony Process **timeout** and **idle timeout**. Bundle default is **180**. Prefer wiring it from the shared env **`PROCESS_TIMEOUT`** (`timeout: '%env(int:PROCESS_TIMEOUT)%'`) so every Nowo bundle that runs external processes uses the same limit — see [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#shared-process_timeout-all-nowo-process-based-bundles). On expiry the runner force-stops the process and attempts to kill LibreOffice children bound to that conversion’s `UserInstallation` profile (important under **FrankenPHP** so workers do not keep orphaned `soffice` processes). Keep PHP `max_execution_time` and the HTTP server write timeout **above** this value.

## Boot check

When `check_on_boot: true` on the default profile, a kernel request listener asserts LibreOffice is available:

- `boot_failure: exception` — throws `MissingDependencyException`
- `boot_failure: warning` — logs a warning and continues

## Flysystem export

`PdfExporter::toFlysystem()` writes to:

1. the service named by the profile's `export.flysystem_adapter` (every id used in `profiles` is exposed to the exporter through a service locator; a missing service fails at container compile, ids not declared in YAML are never reachable; profiles merge over `default`, so setting it there applies to all), or
2. when the profile sets none, the exporter's `$flysystem` argument:

```yaml
services:
    Nowo\WordToPdfBundle\Export\PdfExporter:
        arguments:
            $flysystem: '@your.filesystem.operator.service.id'
```

`ExportException` is thrown when neither is available or the service is not a `League\Flysystem\FilesystemOperator`.

## Deprecated keys

Deprecated since **1.4** (they never had any effect; setting them now triggers a Symfony deprecation, removal planned for the next major):

| Key | Replacement |
|-----|-------------|
| `export.storage` | Pick the target by calling the exporter: `toStreamResponse()` / `toBinaryResponse()` / `toFile()` / `toFlysystem()`. |
| `export.local_path` | Pass the path to `PdfExporter::toFile()`. |

## Sample file

See `src/Resources/config/nowo_word_to_pdf.yaml`.
