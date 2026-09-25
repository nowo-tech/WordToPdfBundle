# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/word-to-pdf-bundle` (`symfony-bundle`) |
| Audited revision | `v1.2.9` (post-remediation) |
| Audit date | 2026-09-25 |
| Method | Manual review of every PHP file under `src/` + PHPStan `ruleset-classic.neon` + `ruleset-worker-no-kernel-reset.neon` |
| Remediation (2026-09-25) | `WordToPdfBundle::getContainerExtension()` made stateless; `RuntimeBootCheckListener` implements `ResetInterface` with a **no-op** `reset()` so the once-per-worker LibreOffice probe survives `services_resetter`; phpstan includes `ruleset-worker-no-kernel-reset.neon` |
| **Verdict** | ✅ **Viable under scenario B** (and A) — no request-scoped state leaks; LibreOffice runs as an isolated child process with explicit timeouts |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: **`FRANKENPHP_RESET_KERNEL` unset/false** — the kernel is **not** rebooted between requests. Two scenarios:

- **A — kernel not rebooted, `services_resetter` still runs:** services implementing `ResetInterface` are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | Only `RuntimeBootCheckListener::$checked` (intentional once-per-worker; `reset()` is a no-op) |
| Static properties / `static` locals | ✅ | None that hold request data |
| `ResetInterface` / `kernel.reset` | ✅ | Boot-check listener implements `ResetInterface`; `reset()` intentionally does **not** clear `$checked` |
| Request / user / locale in services | ✅ | Paths, options and profiles are method arguments |
| Superglobals / `putenv` / `ini_set` | ✅ | Read-only `getenv('PATH')` in binary locator; no process-global writes |
| Doctrine | ✅ N/A | No persistence |
| Resources held open | ✅ | Streams closed in `finally`; conversion workspaces deleted; PDF temps via `dispose()` / exporter |
| Memory growth | ✅ | No caches or accumulating arrays |
| Blocking I/O | ⚠️ Low | `soffice` blocks up to profile `timeout` (default 180 s); documented timeout stack |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + **`ruleset-worker-no-kernel-reset.neon`** in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `WordToPdfConverter` | yes | none (`final readonly`) | ✅ | ✅ |
| `ProfileResolver` | yes | none (`final readonly`) | ✅ | ✅ |
| `LibreOfficeProcessRunner` | yes | none; unique workspace per call | ✅ | ✅ |
| `LibreOfficeBinaryLocator` | yes | none | ✅ | ✅ |
| `RuntimeRequirementsChecker` | yes | none | ✅ | ✅ |
| `PdfExporter` | yes | none (`final readonly`) | ✅ | ✅ |
| `RuntimeBootCheckListener` | yes | `$checked`; `ResetInterface::reset()` no-op | ✅ | ✅ |
| `CheckRuntimeCommand` | yes (CLI) | none | ✅ | ✅ |
| `WordToPdfBundle` | N/A (bundle) | none (stateless `getContainerExtension()`) | ✅ | ✅ |

Value objects (`ResolvedConfig`, `ConvertedPdf`, `PdfNaming`) are per-call and never stored on shared services.

## Findings

### W-01 — LibreOffice conversions pin a worker thread (Low)

- **Where:** `LibreOfficeProcessRunner` + optional `min_version` probe in `RuntimeRequirementsChecker`.
- **Impact:** one thread blocked until timeout; orphans are killed on failure (**REQ-RUNTIME-001**).
- **Recommendation:** keep timeout hierarchy; size worker threads / `max_wait_time`; prefer Messenger for heavy load.

### W-02 — Temporary PDFs require caller dispose (Low)

- **Where:** `ConvertedPdf::dispose()` / exporter / `deleteFileAfterSend`.
- **Recommendation:** always use `PdfExporter` or `dispose()` in `finally`.

### W-03 — Boot check once per worker (Info, by design)

- **Where:** `RuntimeBootCheckListener`; `reset()` no-op under scenario A so the probe is not repeated every request (**REQ-RUNTIME-002**).

## Usage recommendations

- Safe with **`FRANKENPHP_RESET_KERNEL` unset/false**.
- Always dispose `ConvertedPdf` results.
- Do not store conversion results or options on shared services.
- See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) for the timeout stack.

## Re-audit triggers

Persistent LibreOffice (UNO), shared workspaces, new caches/listeners, in-process PDF libraries with static settings, or runtime `putenv()` / `ini_set()`.
