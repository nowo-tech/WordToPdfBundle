# Usage

## Table of contents

- [Convert](#convert)
- [Convert many (batch) + PDF naming](#convert-many-batch--pdf-naming)
- [Export responses](#export-responses)
- [Runtime check](#runtime-check)
- [Standard Symfony host](#standard-symfony-host)
- [DDD / hexagonal host](#ddd--hexagonal-host)
- [Fidelity notes](#fidelity-notes)
- [Forms in host apps and demos (REQ-TWIG-005)](#forms-in-host-apps-and-demos-req-twig-005)

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
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Http\PdfResponseFactory;

// HTTP edge (controllers)
return $responses->binaryFile($pdf); // BinaryFileResponse on a download copy deleted after sending
return $responses->streamed($pdf);   // StreamedResponse, written while it is sent

// Framework-free (services, CLI, Messenger, domain adapters)
$bytes = $writer->toBytes($pdf);
$writer->toStream($pdf, $stream);              // an open, writable resource; you close it
$writer->toFile($pdf, '/path/out.pdf');
$writer->toFlysystem($pdf, 'contracts/out.pdf'); // requires export.flysystem_adapter on the profile (or $flysystem injection)
```

Each call writes the PDF once and disposes it (a temporary PDF is deleted). `PdfResponseFactory` sends the same
`Content-Type` and RFC 6266 `Content-Disposition` headers as the deprecated `ExporterInterface::toStreamResponse()` /
`toBinaryResponse()`. `binaryFile()` copies the PDF to its own download file (the deprecated `toBinaryResponse()`
sent the converted file itself), so a replaced or decorated `PdfWriterInterface` is honoured.

`Export\ExporterInterface` is **deprecated since 1.7.0** and removed in 2.0 (see [UPGRADING](UPGRADING.md#to-170)).

## Runtime check

```php
$this->converter->assertRuntimeReady(); // throws MissingDependencyException if Writer missing
```

CLI:

```bash
php bin/console nowo:word-to-pdf:check
php bin/console nowo:word-to-pdf:check --profile=batch
```

## Standard Symfony host

Zero configuration. Word to PDF is an **infrastructure adapter** around LibreOffice: no Doctrine dependency, no
events, no routes, no entities. Inject `WordToPdfConverterInterface` and `PdfResponseFactory` in a controller and
return the response (see [Export responses](#export-responses)):

```php
public function download(WordToPdfConverterInterface $converter, PdfResponseFactory $responses): Response
{
    return $responses->binaryFile($converter->convert('/path/to/contract.docx'));
}
```

To replace the writer (another storage, audit logging), alias or decorate `PdfWriterInterface`;
`PdfResponseFactory` then writes through your service. The deprecated `ExporterInterface` alias keeps pointing at
`PdfExporter` unless you re-alias it yourself, to a service that implements it (`bin/console lint:container` checks
that).

## DDD / hexagonal host

Opt-in. Hide the bundle behind your own port (e.g. `PdfRenderer`) and implement it in your infrastructure layer with
the framework-free ports `Converter\WordToPdfConverterInterface` and `Export\PdfWriterInterface`; keep
`PdfResponseFactory` in your HTTP adapters (controllers) only.

```php
// Domain / application port (yours)
interface ContractPdfRenderer
{
    public function render(string $contractId, string $docxPath): string; // PDF bytes
}

// Infrastructure adapter (yours)
final readonly class LibreOfficeContractPdfRenderer implements ContractPdfRenderer
{
    public function __construct(
        private WordToPdfConverterInterface $converter,
        private PdfWriterInterface $writer,
    ) {}

    public function render(string $contractId, string $docxPath): string
    {
        $pdf = $this->converter->convertWithOptions($docxPath, ['export' => ['filename' => $contractId . '.pdf']]);

        return $this->writer->toBytes($pdf); // disposes the converted PDF
    }
}
```

- **Ports to type-hint:** `Converter\WordToPdfConverterInterface` and `Export\PdfWriterInterface` (public aliases,
  no Symfony or HTTP type); the result is the `Result\ConvertedPdf` value object (`readContents()`, `path()`,
  `suggestedFilename()`, `dispose()`); errors implement `WordToPdfExceptionInterface`.
- **Replace or decorate:** point an alias at your own service or decorate it
  (`decorates: Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface`, or `PdfWriterInterface`) to add
  logging, metrics, caching or a queue in front of LibreOffice.
- **IDs and events:** none. Name the output from your own IDs with the naming options
  ([Convert many (batch) + PDF naming](#convert-many-batch--pdf-naming)) and dispatch your own domain event from the
  adapter if you need one.
- **Configuration:** named profiles, or `convertWithInlineProfile()` with a profile array your application stores; no
  InstanceSettings or ORM dependency.
- Conversion runs a LibreOffice process synchronously; in a hexagonal app run it from a Messenger handler when the
  request should not wait.
- Tested: `tests/Integration/Kernel/DddHostKernelTest.php` boots this adapter in a host kernel and checks that the
  framework-free ports and the adapter import no Symfony component or Doctrine type.

### Known limitations

- The only engine is LibreOffice; another backend means implementing `WordToPdfConverterInterface` yourself.

## Fidelity notes

LibreOffice Writer export is the self-hosted approach for high layout fidelity. Exotic Word features (some SmartArt, ActiveX, VBA) may not round-trip perfectly. DomPDF/PhpWord PDF writers are **not** used by this bundle.

## Forms in host apps and demos (REQ-TWIG-005)

The bundle ships no page templates of its own; host apps call its services from their own controllers and forms. Every form in host apps and in the demo is a PHP Form Type created in the controller and rendered only with the Twig form helpers and the rendered-aware child loop (REQ-TWIG-003):

```twig
{{ form_start(form) }}
    {% for child in form %}
        {% if not child.rendered %}
            {{ form_row(child) }}
        {% endif %}
    {% endfor %}
    <button type="submit">Submit</button>
{{ form_end(form) }}
```

Hosts and demos must not reintroduce raw `<form>` / `<input>` / `<textarea>` / `<select>` markup; `make check-no-raw-html-form` (part of `make release-check`) fails when bundle or demo Twig contains them.
