<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports;

use Nowo\WordToPdfBundle\Export\ExporterInterface;
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function fwrite;

/** Host exporter on both ports (migration in progress). */
final class DualHostExporter implements PdfWriterInterface, ExporterInterface
{
    public function toBytes(ConvertedPdf $pdf): string
    {
        return '%PDF-dual';
    }

    public function toStream(ConvertedPdf $pdf, mixed $stream): void
    {
        fwrite($stream, '%PDF-dual');
    }

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
