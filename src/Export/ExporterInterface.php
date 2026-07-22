<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Export;

use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports a ConvertedPdf to HTTP responses, local files, or Flysystem.
 */
interface ExporterInterface
{
    /**
     * Stream the PDF as an HTTP response.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     *
     * @return StreamedResponse
     */
    public function toStreamResponse(ConvertedPdf $pdf): StreamedResponse;

    /**
     * Return a BinaryFileResponse for download.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     *
     * @return BinaryFileResponse
     */
    public function toBinaryResponse(ConvertedPdf $pdf): BinaryFileResponse;

    /**
     * Copy the PDF to a local filesystem path.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     * @param string $path Destination path
     *
     * @return void
     */
    public function toFile(ConvertedPdf $pdf, string $path): void;

    /**
     * Upload the PDF via the configured Flysystem operator.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     * @param string $remotePath Remote object path
     *
     * @return void
     */
    public function toFlysystem(ConvertedPdf $pdf, string $remotePath): void;
}
