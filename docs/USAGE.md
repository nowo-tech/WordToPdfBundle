# Usage

## Table of contents

- [Convert](#convert)
- [Convert many (batch) + PDF naming](#convert-many-batch-pdf-naming)
- [Export responses](#export-responses)
- [Runtime check](#runtime-check)
- [Fidelity notes](#fidelity-notes)

## Convert

```php
use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;

public function __construct(
    private WordToPdfConverterInterface $converter,
) {}

public function run(): void
{
    $pdf = $this->converter->convert('/var/docs/offer.docx');
    // or: convertWithProfile($path, 'batch')
    // or: convertWithOptions($path, ['timeout' => 60])
    // or: convertWithInlineProfile($path, ['timeout' => 30, 'export' => ['filename' => 'x.pdf']])

    try {
        file_put_contents('/tmp/offer.pdf', $pdf->readContents());
    } finally {
        $pdf->dispose();
    }
}
```

Supported inputs: `.docx`, `.doc`.

## Convert many (batch) + PDF naming

```php
use Nowo\WordToPdfBundle\Naming\PdfNaming;

// List of paths + naming strategy (default PdfNaming::keep() → {basename}.pdf)
$pdfs = $this->converter->convertMany(
    ['/var/docs/a.docx', '/var/docs/b.docx'],
    PdfNaming::suffix(' [converted]'),
);
// a [converted].pdf, b [converted].pdf

// Prefix / surround
PdfNaming::prefix('OUT-');                      // OUT-contract.pdf
PdfNaming::surround('OUT-', ' [converted]');    // OUT-contract [converted].pdf
PdfNaming::fixed('report.pdf');                 // same name for every item
PdfNaming::callback(fn (string $path, int $i): string => "doc-{$i}");

// Explicit path => filename map (overrides PdfNaming)
$pdfs = $this->converter->convertMany([
    '/var/docs/a.docx' => 'Contract A.pdf',
    '/var/docs/b.docx' => 'Contract B.pdf',
]);
```

`convertMany` is **fail-fast**: if one file fails, previously converted PDFs are `dispose()`d and the exception is rethrown. Single-file methods (`convert`, `convertWithOptions`, …) are unchanged.

## Export responses

```php
use Nowo\WordToPdfBundle\Export\ExporterInterface;

return $exporter->toBinaryResponse($pdf);
// $exporter->toStreamResponse($pdf);
// $exporter->toFile($pdf, '/path/out.pdf');
// $exporter->toFlysystem($pdf, 'contracts/out.pdf'); // requires Flysystem injection
```

## Runtime check

```php
$this->converter->assertRuntimeReady(); // throws MissingDependencyException if Writer missing
```

CLI:

```bash
php bin/console nowo:word-to-pdf:check
php bin/console nowo:word-to-pdf:check --profile=batch
```

## Fidelity notes

LibreOffice Writer export is the self-hosted approach for high layout fidelity. Exotic Word features (some SmartArt, ActiveX, VBA) may not round-trip perfectly. DomPDF/PhpWord PDF writers are **not** used by this bundle.
