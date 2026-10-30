# Coverage policy and exclusions (REQ-TEST-003)

## Gate

| Scope | Command | Gate | Enforced in |
|-------|---------|------|-------------|
| PHP lines (`src/`, PCOV) | `composer coverage-check` | **100%** — fails non-zero below it (`.scripts/coverage-fail-under.sh`) | CI job `coverage` (`.github/workflows/ci.yml`), `make coverage-check` |
| PHP lines (report only) | `composer test-coverage` / `make test-coverage` | prints the percentage; part of `make release-check` | `make release-check` |
| Python (demo fixture generator) | `make test-python` | **90%** lines (`pytest --cov-fail-under=90`, `pytest.ini`) | CI job `python-tests`, `make release-check` |

Reports: `coverage.xml` (Clover, uploaded to Codecov) and `coverage-php.txt` / `coverage-output.txt` (text) at the
repo root; the HTML report goes to `coverage/`, which the root `docker-compose.yml` mounts as the `coverage-data`
volume (REQ-DOC-001).

No `<exclude>` is configured in `phpunit.xml.dist`: every file under `src/` counts.

## Documented `@codeCoverageIgnore` blocks

Only the blocks below are excluded. Each one guards a branch that the test suite cannot reach without breaking
the host (filesystem failures on a writable temp dir, PHP functions disabled at runtime) or that a native type
already makes impossible. **Adding a new `@codeCoverageIgnoreStart` / `@codeCoverageIgnore` requires a new row
here in the same PR**; prefer a test (fixture binary, temp dir with an unsafe character, …) whenever one is possible.

| File | Method | Excluded branch | Why it is not unit-tested |
|------|--------|-----------------|---------------------------|
| `src/Runtime/LibreOfficeProcessRunner.php` | `convert()` | `mkdir()` of the conversion workspace fails | Needs an unwritable temp dir; tests run as root in Docker, where permissions are not enforced. |
| `src/Runtime/LibreOfficeProcessRunner.php` | `convert()` | `mkdir()` of the LibreOffice user profile fails | Same as above (sub-directory of a workspace that was just created). |
| `src/Runtime/LibreOfficeProcessRunner.php` | `convert()` | `copy()` of the source into the workspace fails | The converter validates the source is a readable file just before; only a disk-full / race condition triggers it. |
| `src/Runtime/LibreOfficeProcessRunner.php` | `convert()` | `mkdir()` of the output directory fails | Same as the workspace `mkdir()`. |
| `src/Runtime/LibreOfficeProcessRunner.php` | `convert()` | both `rename()` and `copy()` of the generated PDF fail | Source and target are in the same temp dir; only a disk-full / race condition triggers it. |
| `src/Runtime/LibreOfficeProcessRunner.php` | `forceStopProcess()` | `catch (Throwable)` around `Process::stop()` | Best-effort cleanup; Symfony Process does not throw from `stop()` on a process it started. |
| `src/Runtime/LibreOfficeProcessRunner.php` | `killOrphanedLibreOfficeForProfile()` | early return when the profile path is empty or lacks `word_to_pdf_` | Defensive: the only caller always passes the generated `word_to_pdf_*` profile. |
| `src/Runtime/LibreOfficeProcessRunner.php` | `killOrphanedLibreOfficeForProfile()` | early return when `exec()` is disabled (`disable_functions`) | `disable_functions` cannot be changed at runtime. |
| `src/Runtime/LibreOfficeProcessRunner.php` | `cleanupDir()` | early return when the directory is already gone | Defensive: callers only pass the workspace they created. |
| `src/Runtime/RuntimeRequirementsChecker.php` | `readVersion()` | `mkdir()` of the probe profile fails | Same as the workspace `mkdir()`. |
| `src/Converter/WordToPdfConverter.php` | `assertSource()` | `filesize()` returns `false` | The file was just checked with `is_file()` / `is_readable()`; only a race (file deleted in between) triggers it. |
| `src/Export/PdfExporter.php` | `toStreamResponse()` callback | `fopen()` of the PDF returns `false` | The PDF was produced by the runner just before; only a race triggers it. |
| `src/Export/PdfExporter.php` | `toFile()` | `catch (Throwable)` re-wrapping non-`ExportException` errors | Defensive wrapper; the guarded calls only fail through the `ExportException` path, which is tested. |
| `src/Export/PdfExporter.php` | `toFlysystem()` | `fopen()` of the PDF returns `false` | Same as the streamed response. |
| `src/Naming/PdfNaming.php` | `resolveCallback()` | callback mode without a callable | The named constructor for callback mode requires a `callable`, so the state cannot be built through the public API. |

Lines covered by tests that used to carry an ignore marker (removed in the REQ-TEST-003 pass): the
`safeBasename()` fallback, the file branch of `cleanupDir()`, the unsafe-path early return of
`killOrphanedLibreOfficeForProfile()` (now tested with a temp dir containing a space), and both `return null`
branches of `RuntimeRequirementsChecker::readVersion()` (empty / non-numeric `--version` fixtures).
