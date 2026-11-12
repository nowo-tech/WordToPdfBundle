<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports;

use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;

use function file_put_contents;
use function fwrite;

/** Host writer on the 1.7 port only. */
final class HostPdfWriter implements PdfWriterInterface
{
    public const BYTES = '%PDF-host';

    public function toBytes(ConvertedPdf $pdf): string
    {
        return self::BYTES;
    }

    public function toStream(ConvertedPdf $pdf, mixed $stream): void
    {
        fwrite($stream, self::BYTES);
    }

    public function toFile(ConvertedPdf $pdf, string $path): void
    {
        file_put_contents($path, self::BYTES);
    }

    public function toFlysystem(ConvertedPdf $pdf, string $remotePath): void
    {
    }
}
