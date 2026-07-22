# Installation

## Requirements

- PHP `>=8.2 <8.6`
- Symfony 7 or 8 (`config`, `dependency-injection`, `http-kernel`, `http-foundation`, `console`, `process`)
- **LibreOffice Writer** on the system (`libreoffice-writer` / `soffice`) — **required**

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
