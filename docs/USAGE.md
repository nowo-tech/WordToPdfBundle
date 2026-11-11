# Usage

## Table of contents

- [Convert](#convert)
- [Convert many (batch) + PDF naming](#convert-many-batch--pdf-naming)
- [Export responses](#export-responses)
- [Runtime check](#runtime-check)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
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
use Nowo\WordToPdfBundle\Export\ExporterInterface;

return $exporter->toBinaryResponse($pdf);
// $exporter->toStreamResponse($pdf);
// $exporter->toFile($pdf, '/path/out.pdf');
// $exporter->toFlysystem($pdf, 'contracts/out.pdf'); // requires export.flysystem_adapter on the profile (or $flysystem injection)
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

## Using it from a DDD / hexagonal application

Word to PDF is an **infrastructure adapter** around LibreOffice. It has no Doctrine dependency, no events and no
routes, and it never sees your entities: it takes file paths and returns a `ConvertedPdf` handle.

**Standard Symfony project (default).** Inject `WordToPdfConverterInterface` and `ExporterInterface` in a controller
and return `toStreamResponse()` / `toBinaryResponse()` (see [Export responses](#export-responses)).

**DDD / hexagonal project (opt-in).** Hide the converter behind your own port (e.g. `PdfRenderer`) and depend on
`Converter\WordToPdfConverterInterface`:

```php
final readonly class LibreOfficePdfRenderer implements PdfRenderer
{
    public function __construct(private WordToPdfConverterInterface $converter) {}

    public function fromDocx(string $docxPath): string
    {
        $pdf = $this->converter->convert($docxPath);

        try {
            return $pdf->readContents();
        } finally {
            $pdf->dispose();
        }
    }
}
```

- **Ports to type-hint:** `Converter\WordToPdfConverterInterface` (application/infrastructure side) and
  `Export\ExporterInterface` (both public aliases); the result is the `Result\ConvertedPdf` value object
  (`readContents()`, `path()`, `suggestedFilename()`, `dispose()`); errors implement `WordToPdfExceptionInterface`.
- **Split the exporter by layer:** `ExporterInterface::toStreamResponse()` / `toBinaryResponse()` return Symfony HTTP
  responses, so call them only from controllers (presentation). In application or infrastructure code use
  `toFile()` / `toFlysystem()` or `ConvertedPdf::readContents()` / `path()`.
- **Replace or decorate:** point an alias at your own service or decorate it
  (`decorates: Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface`) to add logging, metrics, caching or a
  queue in front of LibreOffice.
- **IDs and events:** none. Name the output from your own IDs with the naming options
  ([Convert many (batch) + PDF naming](#convert-many-batch--pdf-naming)) and dispatch your own domain event from the
  adapter if you need one.
- Conversion runs a LibreOffice process synchronously; in a hexagonal app run it from a Messenger handler when the
  request should not wait.

What stays standard-only: the HTTP response helpers of `ExporterInterface` (presentation layer).

### Known limitations

- The only engine is LibreOffice; another backend means implementing `WordToPdfConverterInterface` yourself.
- `ExporterInterface` mixes HTTP response helpers and storage methods in one interface; keep the HTTP ones in
  controllers.

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
