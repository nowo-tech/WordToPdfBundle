# FrankenPHP demo

The repository includes an **optional Symfony demo app** under `demo/symfony8` (FrankenPHP + Docker Compose + LibreOffice). It is excluded from the Packagist package via `archive.exclude`.

| Demo | PHP | Default HTTP port |
|------|-----|-------------------|
| `demo/symfony8` | 8.4 | 8022 |

See [`demo/README.md`](../demo/README.md) for quick start and aggregate `make` targets.

## Quick start

```bash
cd demo/symfony8
cp .env.example .env
make up
# Demo started at: http://localhost:8022
```

## Development vs production (FrankenPHP worker mode)

The demo ships two Caddy configurations under `docker/frankenphp/`:

| File | FrankenPHP worker | Typical use |
|------|-------------------|-------------|
| `Caddyfile.dev` | **Off** (one PHP process per request) | Local demo (`APP_ENV=dev`, default in `docker-compose.yml`) |
| `Caddyfile` | **On** (`worker /app/public/index.php`) | Production-style (`APP_ENV=prod`, `APP_DEBUG=0`) |

The container entrypoint copies `Caddyfile.dev` over the default Caddyfile when `APP_ENV=dev`, so **`make up` runs without worker mode**. For production-style behaviour, set `APP_ENV=prod` in `.env` and rebuild/restart the demo container so FrankenPHP keeps workers in memory.

## Demo page

The demo exposes `/` that:

1. Shows LibreOffice Writer **runtime check** status (`RuntimeRequirementsChecker::diagnose()`).
2. Accepts upload of `.docx` / `.doc` and returns a PDF download.
3. Offers **Download sample.docx as PDF** (`/sample.pdf`).

Dev stack includes Web Profiler and **Nowo Twig Inspector**.

## LibreOffice in the image

The demo `Dockerfile` installs LibreOffice (`apk add libreoffice` on Alpine). Without it, `nowo:word-to-pdf:check` exits `1` and conversion throws `MissingDependencyException` with install hints.

## Troubleshooting

| Symptom | What to try |
|---------|-------------|
| `LibreOffice Writer is missing` | Rebuild the demo image; confirm `soffice` inside the container (`docker compose exec php which soffice`). |
| Conversion timeout | Increase `timeout` in `config/packages/nowo_word_to_pdf.yaml`; check container CPU/memory. |
| Port already in use | Change `PORT` in `.env` / `.env.example`. |
| Composer cannot resolve Packagist | Demo compose sets `dns: 8.8.8.8` / `8.8.4.4` (REQ-DEMO-009). |
| Twig/PHP changes not visible | Ensure `APP_ENV=dev` so `Caddyfile.dev` (no worker) is used. |

## Root vs demo Docker

The `Dockerfile` and `docker-compose.yml` at the **repository root** are for **developer QA** (`make up`, Composer, PHPUnit, PHPStan) — not for serving the demo.

Conventions (Nowo bundles): Web Profiler + Twig Inspector in dev, path repository to the mounted bundle, `make up` prints `Demo started at: http://localhost:<PORT>`, Composer DNS fallbacks in compose when needed.
