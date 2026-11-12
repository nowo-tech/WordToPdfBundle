<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports;

use Nowo\WordToPdfBundle\Export\ExporterInterface;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Host exporter still on the deprecated port only. */
final class LegacyHostExporter implements ExporterInterface
{
    public function toStreamResponse(ConvertedPdf $pdf): StreamedResponse
    {
        return new StreamedResponse();
    }

    public function toBinaryResponse(ConvertedPdf $pdf): BinaryFileResponse
    {
        return new BinaryFileResponse(__FILE__);
    }

    public function toFile(ConvertedPdf $pdf, string $path): void
    {
    }

    public function toFlysystem(ConvertedPdf $pdf, string $remotePath): void
    {
    }
}
