# Usage

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
