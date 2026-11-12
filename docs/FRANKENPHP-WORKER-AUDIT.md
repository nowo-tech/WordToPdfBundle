# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/word-to-pdf-bundle` (`symfony-bundle`) |
| Audited revision | `v1.7.1` (first audit `v1.2.9`, post-remediation; REQ-RUNTIME-002 re-audit for 1.7.1) |
| Audit date | 2026-09-25; re-audited 2026-11-12 |
| Method | Manual review of every PHP file under `src/` + PHPStan `ruleset-classic.neon` + `ruleset-worker-no-kernel-reset.neon` |
| Remediation (2026-09-25) | `WordToPdfBundle::getContainerExtension()` made stateless; `RuntimeBootCheckListener` implements `ResetInterface` with a **no-op** `reset()` so the once-per-worker LibreOffice probe survives `services_resetter`; phpstan includes `ruleset-worker-no-kernel-reset.neon` |
| Remediation (2026-10-01) | `$checked` marked `#[WorkerSafe]` so Igor IncompleteReset is silent while the probe remains once-per-worker |
| Remediation (2026-11-12, 1.7.1) | REQ-RUNTIME-002 worker test added (`tests/Integration/Kernel/WorkerKeptAliveKernelTest.php`); the binary-version memo is documented as per-process state |
| **Verdict** | ✅ **Safe with kernel reset false** (scenario B, and A) — the bundle works in FrankenPHP worker mode with `FRANKENPHP_RESET_KERNEL=false` and without `services_resetter`: no request-scoped state leaks, temporary files are removed per call, memory stays flat; LibreOffice runs as an isolated child process with explicit timeouts |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Services reviewed](#services-reviewed)
- [Findings](#findings)
  - [W-01 — LibreOffice conversions pin a worker thread (Low)](#w-01--libreoffice-conversions-pin-a-worker-thread-low)
  - [W-02 — Temporary PDFs require caller dispose (Low)](#w-02--temporary-pdfs-require-caller-dispose-low)
  - [W-03 — Boot check once per worker (Info, by design)](#w-03--boot-check-once-per-worker-info-by-design)
- [Worker test](#worker-test)
- [Usage recommendations](#usage-recommendations)
- [Re-audit triggers](#re-audit-triggers)

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: **`FRANKENPHP_RESET_KERNEL` unset/false** — the kernel is **not** rebooted between requests. Two scenarios:

- **A — kernel not rebooted, `services_resetter` still runs:** services implementing `ResetInterface` are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `RuntimeBootCheckListener::$checked` (intentional once-per-worker; `#[WorkerSafe]`; `reset()` is a no-op) and the `RuntimeRequirementsChecker` binary-version memo (per process, see below); no request data |
| Static properties / `static` locals | ✅ | None that hold request data |
| `ResetInterface` / `kernel.reset` | ✅ | Boot-check listener implements `ResetInterface`; `reset()` intentionally does **not** clear `$checked` |
| Request / user / locale in services | ✅ | Paths, options and profiles are method arguments |
| Superglobals / `putenv` / `ini_set` | ✅ | Read-only `getenv('PATH')` in binary locator; no process-global writes |
| Doctrine | ✅ N/A | No persistence |
| Resources held open | ✅ | Streams closed in `finally`; conversion workspaces deleted; PDF temps via `dispose()` / exporter |
| Memory growth | ✅ | No request caches or accumulating arrays; the version memo is bounded by the configured binaries. Proven flat by `WorkerKeptAliveKernelTest` (< 2 MiB over 270 conversions) |
| Worker test (kernel reset false) | ✅ | `tests/Integration/Kernel/WorkerKeptAliveKernelTest.php` (see [Worker test](#worker-test)) |
| Blocking I/O | ⚠️ Low | `soffice` blocks up to profile `timeout` (default 180 s); documented timeout stack |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + **`ruleset-worker-no-kernel-reset.neon`** in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `WordToPdfConverter` | yes | none (`final readonly`) | ✅ | ✅ |
| `ProfileResolver` | yes | none (`final readonly`) | ✅ | ✅ |
| `LibreOfficeProcessRunner` | yes | none; unique workspace per call | ✅ | ✅ |
| `LibreOfficeBinaryLocator` | yes | none | ✅ | ✅ |
| `RuntimeRequirementsChecker` | yes | `$versions`: binary path => LibreOffice version, filled only by a successful `soffice --version` probe (profiles with `min_version`, `diagnose()`). Per-process memo, not request data: no user, tenant, document or path of a conversion; failures are not memoized. Cleared only by `reset()` (`kernel.reset`), so with kernel reset false it lives for the worker's lifetime; accepted (a LibreOffice upgrade is picked up on worker restart) | ✅ reset | ✅ (memo kept by design) |
| `PdfExporter` | yes | none (`final readonly`) | ✅ | ✅ |
| `PdfResponseFactory` (1.7.0) | yes | none (`final readonly`, only the `PdfWriterInterface` dependency; the download copy is deleted after sending) | ✅ | ✅ |
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

## Worker test

`tests/Integration/Kernel/WorkerKeptAliveKernelTest.php` (REQ-RUNTIME-002) boots **one** `HostKernel` (variant
`worker`) and never calls the services resetter. It runs **N = 300** conversions through the fake `soffice`
(`tests/Fixtures/fake-soffice.sh`; one child process per conversion, so N is moderate) as two tenant profiles in
alternation: `alpha` (own temp dir, `min_version`, download name `alpha-invoice.pdf`) and `beta` (own temp dir,
`beta-contract.pdf`). For every conversion it asserts the tenant's own download name and temp dir, that
`PdfWriterInterface::toBytes()` returns the PDF and disposes it, and that neither tenant's temp dir holds a result PDF or
a conversion workspace afterwards. Memory margin: less than 2 MiB growth after a 30-conversion warm-up. At the end the
version memo holds exactly one entry (the fake binary) and no `word_to_pdf_version_*` probe profile is left.

## Usage recommendations

- Safe with **kernel reset false** (`FRANKENPHP_RESET_KERNEL` unset/false), with or without `services_resetter`.
- Always dispose `ConvertedPdf` results.
- Do not store conversion results or options on shared services.
- See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) for the timeout stack.

## Re-audit triggers

Persistent LibreOffice (UNO), shared workspaces, new caches/listeners, in-process PDF libraries with static settings, or runtime `putenv()` / `ini_set()`.
