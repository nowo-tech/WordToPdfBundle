<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Export;

use Nowo\WordToPdfBundle\Http\PdfResponseFactory;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports a ConvertedPdf to HTTP responses, local files, or Flysystem.
 *
 * @deprecated since 1.7.0, removed in 2.0. It mixes HttpFoundation responses with storage (REQ-ARCH-001 rule 3):
 *             write PDFs through {@see PdfWriterInterface} and build downloads with
 *             {@see PdfResponseFactory}. Hosts implementing it implement
 *             {@see PdfWriterInterface} instead.
 */
interface ExporterInterface
{
    /**
     * Stream the PDF as an HTTP response.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     *
     * @return StreamedResponse
     *
     * @deprecated since 1.7.0, use {@see PdfResponseFactory::streamed()}
     */
    public function toStreamResponse(ConvertedPdf $pdf): StreamedResponse;

    /**
     * Return a BinaryFileResponse for download.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     *
     * @return BinaryFileResponse
     *
     * @deprecated since 1.7.0, use {@see PdfResponseFactory::binaryFile()}
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
