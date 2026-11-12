<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\DddHost\Infrastructure;

use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\DddHost\Domain\ContractPdfRenderer;

/** Host infrastructure adapter: implements the domain port with the bundle's framework-free ports only. */
final readonly class LibreOfficeContractPdfRenderer implements ContractPdfRenderer
{
    public function __construct(
        private WordToPdfConverterInterface $converter,
        private PdfWriterInterface $writer,
    ) {
    }

    public function render(string $contractId, string $docxPath): string
    {
        $pdf = $this->converter->convertWithOptions($docxPath, ['export' => ['filename' => $contractId . '.pdf']]);

        return $this->writer->toBytes($pdf);
    }
}
