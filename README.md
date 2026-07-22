# WordToPdfBundle

[![CI](https://github.com/nowo-tech/WordToPdfBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/WordToPdfBundle/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/word-to-pdf-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/word-to-pdf-bundle)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/word-to-pdf-bundle.svg)](https://packagist.org/packages/nowo-tech/word-to-pdf-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com)
[![GitHub stars](https://img.shields.io/github/stars/nowo-tech/word-to-pdf-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/WordToPdfBundle)
[![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> **Found this useful?** Install from Packagist (`composer require nowo-tech/word-to-pdf-bundle`) and consider starring [WordToPdfBundle on GitHub](https://github.com/nowo-tech/WordToPdfBundle).

Symfony bundle that converts **Microsoft Word** (`.docx` / `.doc`) to **PDF** using **LibreOffice Writer** (`soffice` headless) for print-quality layout fidelity:

- **named YAML profiles** + **default profile** + deep merge with per-call options, or **`convertWithInlineProfile()`**;
- **runtime check** that **LibreOffice Writer** is installed (`nowo:word-to-pdf:check`); fails with install hints if missing;
- works under **PHP-FPM** and **FrankenPHP** (Symfony Process / `proc_open`);
- Symfony-friendly export: streamed/binary responses, local path, optional **Flysystem**.

This bundle does **not** fill Word templates (see [WordTemplateBundle](https://github.com/nowo-tech/WordTemplateBundle)), convert HTML to Word (see [HtmlToWordBundle](https://github.com/nowo-tech/HtmlToWordBundle)), or use DomPDF (DomPDF cannot preserve Word styles).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [GitHub Actions CI requirements](docs/GITHUB_CI.md)
- [FrankenPHP / Docker demo](docs/DEMO-FRANKENPHP.md) — `demo/symfony8` (see [`demo/README.md`](demo/README.md))

## System requirement

**LibreOffice Writer must be installed on the host / container** (Composer cannot install it):

```bash
# Debian / Ubuntu
sudo apt-get install -y libreoffice-writer

# Alpine
apk add libreoffice

# Fedora / RHEL
sudo dnf install -y libreoffice-writer
```

Verify:

```bash
php bin/console nowo:word-to-pdf:check
```

## Quick start

```bash
composer require nowo-tech/word-to-pdf-bundle
php bin/console nowo:word-to-pdf:check
```

```yaml
# config/packages/nowo_word_to_pdf.yaml
nowo_word_to_pdf:
    default_profile: default
    profiles:
        default:
            timeout: 120
            export:
                filename: document.pdf
```

```php
use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;
use Nowo\WordToPdfBundle\Export\ExporterInterface;

public function download(WordToPdfConverterInterface $converter, ExporterInterface $exporter): Response
{
    $pdf = $converter->convert('/path/to/contract.docx');

    return $exporter->toBinaryResponse($pdf);
}
```

## FrankenPHP worker mode

FrankenPHP worker mode: Supported (tested with LibreOffice conversion under FrankenPHP).

The demo runs under FrankenPHP. Conversion uses Symfony Process with a hard **timeout** (and idle timeout); on expiry the runner stops the process tree so workers are not left with orphaned LibreOffice children (**REQ-RUNTIME-001**). Align PHP / Caddy deadlines above the profile timeout.

Demos use **`FRANKENPHP_MODE`** (`worker` by default, or `classic`) on PHP **8.5** — see [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md). Change mode in `.env` and recreate containers (`docker compose up -d`); no image rebuild.

```bash
cd demo/symfony8 && cp .env.example .env && make up   # Symfony 8, port 8022
```

## Tests and coverage

| Scope | Detail |
|-------|--------|
| **PHPUnit** | `composer test` — unit + integration (`@group libreoffice` skipped when `soffice` is absent). |
| **PHP lines** | `composer coverage-check` enforces **100%** (PCOV). Latest measurement: **PHP: 100%**. |
| **TS/JS** | N/A (no frontend assets in this bundle). |

```bash
composer test
composer coverage-check
```

## Development

```bash
make up
make qa
make release-check
```
