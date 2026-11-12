<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports;

use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;

/** Host decorator of the 1.7 port (e.g. audit logging around the bundle writer). */
final readonly class DecoratingPdfWriter implements PdfWriterInterface
{
    public function __construct(
        public PdfWriterInterface $inner,
    ) {
    }

    public function toBytes(ConvertedPdf $pdf): string
    {
        return $this->inner->toBytes($pdf);
    }

    public function toStream(ConvertedPdf $pdf, mixed $stream): void
    {
        $this->inner->toStream($pdf, $stream);
    }

    public function toFile(ConvertedPdf $pdf, string $path): void
    {
        $this->inner->toFile($pdf, $path);
    }

    public function toFlysystem(ConvertedPdf $pdf, string $remotePath): void
    {
        $this->inner->toFlysystem($pdf, $remotePath);
    }
}
