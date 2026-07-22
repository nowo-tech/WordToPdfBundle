<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Export;

use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExporterInterface
{
    public function toStreamResponse(ConvertedPdf $pdf): StreamedResponse;

    public function toBinaryResponse(ConvertedPdf $pdf): BinaryFileResponse;

    public function toFile(ConvertedPdf $pdf, string $path): void;

    public function toFlysystem(ConvertedPdf $pdf, string $remotePath): void;
}
