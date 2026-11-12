<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Export;

use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;
use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Http\PdfResponseFactory;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;

/**
 * Framework-free output port for a {@see ConvertedPdf} (REQ-ARCH-001 rule 3): bytes, a stream, a local path or the
 * configured Flysystem adapter. No Symfony or HTTP type in its signatures, so a CLI command, a Messenger handler or a
 * DDD / hexagonal adapter can depend on it (with {@see WordToPdfConverterInterface})
 * without HttpFoundation.
 *
 * Every method writes the PDF once and then disposes it ({@see ConvertedPdf::dispose()} deletes a temporary PDF).
 * HTTP downloads are built at the edge by {@see PdfResponseFactory}.
 *
 * Since 1.7.0; replaces the storage half of the deprecated {@see ExporterInterface} (removed in 2.0).
 */
interface PdfWriterInterface
{
    /**
     * Returns the PDF as a binary string.
     *
     * @throws ExportException
     */
    public function toBytes(ConvertedPdf $pdf): string;

    /**
     * Writes the PDF into a writable stream the caller opened (and closes). The stream is not rewound.
     *
     * @param resource $stream
     *
     * @throws ExportException
     */
    public function toStream(ConvertedPdf $pdf, mixed $stream): void;

    /**
     * Copies the PDF to a local path.
     *
     * @throws ExportException
     */
    public function toFile(ConvertedPdf $pdf, string $path): void;

    /**
     * Uploads the PDF to the profile's {@code export.flysystem_adapter} (or the injected default operator).
     *
     * @throws ExportException
     */
    public function toFlysystem(ConvertedPdf $pdf, string $remotePath): void;
}
