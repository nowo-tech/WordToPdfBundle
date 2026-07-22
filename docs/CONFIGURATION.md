# Configuration

Root key: `nowo_word_to_pdf`.

## Profiles (REQ-CFG)

```yaml
nowo_word_to_pdf:
    engine: libreoffice
    default_profile: default
    profiles:
        default:
            binary_path: null          # null = auto-detect soffice/libreoffice
            temp_dir: null             # null = sys_get_temp_dir()
            timeout: 120               # seconds
            max_source_bytes: 52428800 # 50 MiB
            check_on_boot: false
            boot_failure: exception    # exception|warning
            min_version: null          # e.g. "24.2"
            filter: pdf:writer_pdf_Export
            export:
                filename: document.pdf
                storage: memory
                local_path: null
                flysystem_adapter: null
        batch:
            timeout: 300
            export:
                filename: batch.pdf
```

- `default_profile` **must** exist as a key under `profiles`.
- Merge order for `convertWithOptions()`: default profile → named profile → ad-hoc options (deepest wins).
- `convertWithInlineProfile()` uses only the provided array (no YAML merge).
- **`timeout`** (seconds) is applied as Symfony Process **timeout** and **idle timeout**. On expiry the runner force-stops the process and attempts to kill LibreOffice children bound to that conversion’s `UserInstallation` profile (important under **FrankenPHP** so workers do not keep orphaned `soffice` processes). Keep PHP `max_execution_time` and the HTTP server write timeout **above** this value — see [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#timeouts-avoid-stuck-frankenphp-workers--orphaned-soffice).

## Boot check

When `check_on_boot: true` on the default profile, a kernel request listener asserts LibreOffice is available:

- `boot_failure: exception` — throws `MissingDependencyException`
- `boot_failure: warning` — logs a warning and continues

## Sample file

See `src/Resources/config/nowo_word_to_pdf.yaml`.
