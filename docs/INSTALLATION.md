# Installation

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Optional: Flysystem](#optional-flysystem)
- [Verify runtime](#verify-runtime)
- [PHP runtime notes (FPM & FrankenPHP)](#php-runtime-notes-fpm-frankenphp)

## Requirements

- PHP `>=8.2 <8.6`
- Symfony 7.4+ or 8 (`config`, `dependency-injection`, `http-kernel`, `http-foundation`, `console`, `process`, `yaml`)
- **LibreOffice Writer** on the system (`libreoffice-writer` / `soffice`) — **required**
- [League Flysystem](https://github.com/thephpleague/flysystem) 3.x — optional, not installed by the bundle; only needed for `PdfExporter::toFlysystem()`

### Install LibreOffice Writer

| Distro | Command |
|--------|---------|
| Debian / Ubuntu | `sudo apt-get install -y libreoffice-writer` |
| Alpine | `apk add libreoffice` |
| Fedora / RHEL | `sudo dnf install -y libreoffice-writer` |

Confirm the binary exists (`/usr/bin/soffice` or `/usr/bin/libreoffice`).

## Composer

```bash
composer require nowo-tech/word-to-pdf-bundle
```

With Symfony Flex, the recipe copies `config/packages/nowo_word_to_pdf.yaml`.

Without Flex, register the bundle and copy the sample from `vendor/nowo-tech/word-to-pdf-bundle/src/Resources/config/nowo_word_to_pdf.yaml`.

## Optional: Flysystem

`league/flysystem` is not installed with the bundle. To upload PDFs to a remote or custom filesystem, run `composer require league/flysystem`, define a `League\Flysystem\FilesystemOperator` service and either reference it from a profile (`export.flysystem_adapter: 'your.storage.id'`) or pass it as the `$flysystem` argument of `Nowo\WordToPdfBundle\Export\PdfExporter` (see [CONFIGURATION.md](CONFIGURATION.md#flysystem-export)).

## Verify runtime

```bash
php bin/console nowo:word-to-pdf:check
```

Exit code `0` means LibreOffice Writer is ready. Exit code `1` prints install instructions.

## PHP runtime notes (FPM & FrankenPHP)

- `proc_open` must not be listed in `disable_functions` (Symfony Process).
- The PHP user must be able to execute `soffice` and write to the configured temp directory.
- Prefer installing fonts used by your documents inside the container/image for best fidelity.
  Without matching fonts, LibreOffice often exports **tofu boxes (□□□)** instead of glyphs.
  The FrankenPHP demo image installs Liberation, DejaVu, and Noto (incl. CJK/emoji) for this reason.
- **FrankenPHP worker** with **`FRANKENPHP_RESET_KERNEL` unset/false** is supported (stateless shared services; see [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#worker-mode-without-kernel-reset) and [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md)).
