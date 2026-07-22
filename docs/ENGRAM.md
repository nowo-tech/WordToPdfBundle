# Engram — WordToPdfBundle

## Purpose

High-fidelity Word (`.docx`/`.doc`) → PDF via LibreOffice Writer (`soffice`), with named profiles and mandatory runtime dependency checks.

## Non-goals

- DomPDF / PhpWord PDF writers
- Filling Word templates (WordTemplateBundle)
- HTML → Word (HtmlToWordBundle)
- Cloud converters (Graph / Aspose) in v1

## Runtime

- System package: `libreoffice-writer` (or Alpine `libreoffice`)
- PHP: Symfony Process (`proc_open`) — PHP-FPM and FrankenPHP
- CLI: `nowo:word-to-pdf:check`

## Key types

- `WordToPdfConverterInterface` / `WordToPdfConverter`
- `RuntimeRequirementsChecker` / `LibreOfficeProcessRunner`
- `ConvertedPdf` / `PdfExporter`
- `MissingDependencyException` when Writer is missing
