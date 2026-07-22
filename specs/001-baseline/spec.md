# Spec: WordToPdfBundle baseline

## Summary

Convert `.docx` / `.doc` to PDF via LibreOffice Writer with named profiles and mandatory runtime dependency checks.

## Requirements

1. **REQ-CONVERT**: Given a readable Word file and a ready LibreOffice binary, `WordToPdfConverterInterface::convert()` returns a temporary PDF (`%PDF` magic).
2. **REQ-CHECK**: When LibreOffice Writer is missing, `RuntimeRequirementsChecker` / `nowo:word-to-pdf:check` report a clear message including `libreoffice-writer` install hints and fail (exception / exit 1).
3. **REQ-CFG**: Configuration uses `default_profile` + `profiles`; invalid default fails container compilation.
4. **REQ-RUNTIME**: Conversion uses Symfony Process (PHP-FPM and FrankenPHP compatible).
5. **REQ-FORMAT**: Unsupported extensions throw `UnsupportedFormatException`.

## Out of scope

- HTML→PDF, template mail-merge, Microsoft Graph / Aspose adapters, async Messenger.
