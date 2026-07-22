# Upgrading

## Unreleased

*(none yet)*

## To 1.0.0 (initial release)

This is the first stable release. Install or require the package:

```bash
composer require nowo-tech/word-to-pdf-bundle:^1.0
```

### Requirements

- PHP `>= 8.2`, `< 8.6`
- Symfony `^7.0 || ^8.0` (CI covers 7.4, 8.0, 8.1)
- **LibreOffice Writer** installed on every environment that converts documents (`apt install libreoffice-writer`, `apk add libreoffice`, etc.)
- `proc_open` not listed in `disable_functions` (Symfony Process)

### Enable and configure

Symfony Flex registers the bundle and copies `config/packages/nowo_word_to_pdf.yaml`. Manual setup:

```php
// config/bundles.php
Nowo\WordToPdfBundle\WordToPdfBundle::class => ['all' => true],
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

Verify the runtime:

```bash
php bin/console nowo:word-to-pdf:check
```

### Public API

- `Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface` — `convert()`, `convertWithProfile()`, `convertWithOptions()`, `convertWithInlineProfile()`, `assertRuntimeReady()`
- `Nowo\WordToPdfBundle\Export\ExporterInterface` — stream / binary / file / Flysystem helpers

See [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).

### Integrator notes

- Config root key is **`nowo_word_to_pdf`** (not a short alias).
- Dispose converted PDFs when finished (`ConvertedPdf::dispose()`) so temp workspaces are cleaned up.
- DomPDF / PhpWord PDF writers are **not** used; layout fidelity comes from LibreOffice Writer export.
- Related packages (optional): [WordTemplateBundle](https://github.com/nowo-tech/WordTemplateBundle), [HtmlToWordBundle](https://github.com/nowo-tech/HtmlToWordBundle).

### Breaking changes

None (initial release).

## Version 1.x

Future breaking changes will be listed here with migration steps.
