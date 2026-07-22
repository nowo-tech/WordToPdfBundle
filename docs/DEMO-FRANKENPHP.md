# FrankenPHP demo

The repository includes an **optional Symfony demo app** under `demo/symfony8` (FrankenPHP + Docker Compose + LibreOffice). It is excluded from the Packagist package via `archive.exclude`.

## Table of contents

- [Quick start](#quick-start)
- [PHP version and FRANKENPHP_MODE](#php-version-and-frankenphp_mode)
- [Development vs production (FrankenPHP worker mode)](#development-vs-production-frankenphp-worker-mode)
- [Timeouts (avoid stuck FrankenPHP workers / orphaned soffice)](#timeouts-avoid-stuck-frankenphp-workers--orphaned-soffice)
- [Demo page](#demo-page)
- [LibreOffice in the image](#libreoffice-in-the-image)
- [Troubleshooting](#troubleshooting)
- [Root vs demo Docker](#root-vs-demo-docker)

| Demo | PHP | Default HTTP port |
|------|-----|-------------------|
| `demo/symfony8` | **8.5** (FrankenPHP image) | 8022 |

See [`demo/README.md`](../demo/README.md) for quick start and aggregate `make` targets.

## Quick start

```bash
cd demo/symfony8
cp .env.example .env
make up
# Demo started at: http://localhost:8022
```

## PHP version and FRANKENPHP_MODE

Per **REQ-DEMO-010**:

1. The Symfony 8 demo uses the newest official FrankenPHP PHP tag compatible with `composer.json` (`dunglas/frankenphp:1-php8.5-alpine` as of mid-2026).
2. Runtime mode is selected with **`FRANKENPHP_MODE`** in `.env` / Compose (**not** baked into the Docker image):
   - **`worker`** (default) — long-lived workers (`Caddyfile` with `php_server { worker … }`)
   - **`classic`** — one PHP process per request (`Caddyfile.dev`) for easier hot-reload

```bash
# .env
FRANKENPHP_MODE=worker   # or classic
```

After changing `FRANKENPHP_MODE`, recreate containers (`docker compose up -d`) — no image rebuild required. Rebuild only when the Dockerfile PHP tag changes.

## Development vs production (FrankenPHP worker mode)

The demo ships two Caddy configurations under `docker/frankenphp/`:

| File | FrankenPHP worker | Typical use |
|------|-------------------|-------------|
| `Caddyfile` | **On** (`worker /app/public/index.php`) | `FRANKENPHP_MODE=worker` (default) |
| `Caddyfile.dev` | **Off** (one PHP process per request) | `FRANKENPHP_MODE=classic` |

The entrypoint (`docker/entrypoint.sh`) branches on `FRANKENPHP_MODE` (default **worker**).

## Timeouts (avoid stuck FrankenPHP workers / orphaned soffice)

LibreOffice conversion is a **blocking** `proc_open` call. Under FrankenPHP a hung conversion can occupy a worker thread and leave `soffice.bin` children behind. Keep this hierarchy (**REQ-RUNTIME-001**):

| Layer | Demo default | Role |
|-------|--------------|------|
| `nowo_word_to_pdf.profiles.*.timeout` | **180s** | Symfony Process wall-clock **and** idle timeout; on expiry the runner `stop(0)`s the process and `pkill`s orphans matching the conversion `UserInstallation` profile |
| PHP `max_execution_time` / `max_input_time` | **240s** | Set via `frankenphp { php_ini … }` and `docker/php-dev.ini` — must be **greater** than the profile timeout so Process can fire first |
| Caddy `servers.timeouts.write` | **250s** | HTTP write deadline above PHP |
| FrankenPHP `max_wait_time` | **30s** | Max time a request waits for a free PHP thread before **504** (limits backlog when workers are busy converting) |

When raising `timeout` in YAML, raise PHP + Caddy write timeouts in the same step.

## Demo page

The demo exposes `/` that:

1. Shows LibreOffice Writer **runtime check** status (`RuntimeRequirementsChecker::diagnose()`).
2. Highlights **`/stress.pdf`** — converts `public/demo/stress-styles.docx`, a fidelity gauntlet (images, tables, styles, sections, columns, headers/footers, fields, unicode). The demo home page lists the full inventory.
3. Accepts upload of `.docx` / `.doc` and returns a PDF download.
4. Offers a minimal **`/sample.pdf`** (`sample.docx`).

Regenerate the stress DOCX when needed (requires `python-docx` + Pillow):

```bash
python3 demo/symfony8/bin/generate-stress-docx.py
```

Dev stack includes Web Profiler and **Nowo Twig Inspector**.

## LibreOffice in the image

The demo `Dockerfile` installs LibreOffice (`apk add libreoffice` on Alpine) **plus font packs** (`font-dejavu`, `font-liberation`, `font-noto`, `font-noto-emoji`, `font-noto-cjk`) and runs `fc-cache`. Without fonts, exported PDF text often becomes empty boxes (□□□) because Writer cannot embed the requested typefaces.

The stress sample uses **Liberation Sans / Serif / Mono** and **DejaVu Sans Mono** (present in the image) instead of Microsoft fonts such as Calibri/Georgia.

Without LibreOffice itself, `nowo:word-to-pdf:check` exits `1` and conversion throws `MissingDependencyException` with install hints.

## Troubleshooting

| Symptom | What to try |
|---------|-------------|
| Tofu boxes (□□□) instead of text | Install fonts in the image (`font-dejavu`, `font-liberation`, …) and rebuild; match document font names to installed families. |
| `LibreOffice Writer is missing` | Rebuild the demo image; confirm `soffice` inside the container (`docker compose exec php which soffice`). |
| Conversion timeout / stuck workers | Keep `timeout` &lt; PHP `max_execution_time` &lt; Caddy `write` (see [Timeouts](#timeouts-avoid-stuck-frankenphp-workers--orphaned-soffice)); rebuild if `pkill`/procps missing. |
| Port already in use | Change `PORT` in `.env` / `.env.example`. |
| Composer cannot resolve Packagist | Demo compose sets `dns: 8.8.8.8` / `8.8.4.4` with a comment (REQ-DEMO-009). |
| Twig/PHP changes not visible | Set `FRANKENPHP_MODE=classic` and recreate (`docker compose up -d`) so `Caddyfile.dev` is used. |
| Unknown `FRANKENPHP_MODE` | Entrypoint exits; set `classic` or `worker` in `.env`. |

## Root vs demo Docker

The `Dockerfile` and `docker-compose.yml` at the **repository root** are for **developer QA** (`make up`, Composer, PHPUnit, PHPStan) — not for serving the demo.

Conventions (Nowo bundles): Web Profiler + Twig Inspector in dev, path repository to the mounted bundle, `make up` prints `Demo started at: http://localhost:<PORT>`, Composer DNS fallbacks in compose when needed.
