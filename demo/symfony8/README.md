# WordToPdfBundle — Symfony 8 demo (FrankenPHP + LibreOffice Writer)

See also [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md) for worker mode, troubleshooting, and conventions.

## Start

```bash
cp .env.example .env
make up
# Demo started at: http://localhost:8022
```

Default `FRANKENPHP_MODE` is **`worker`**. Set `classic` in `.env` and recreate (`docker compose up -d`) for per-request PHP (no rebuild).

## What it shows

- Upload Word → PDF download
- Runtime status for LibreOffice Writer
- Sample conversion of `public/demo/sample.docx`
- Stress sample `public/demo/stress-styles.docx` (complex styles / layout)
- Dev tools: Web Profiler + Twig Inspector

## System package

The Dockerfile installs LibreOffice (`apk add libreoffice`) plus common fonts. Without Writer, conversion fails with install instructions.
