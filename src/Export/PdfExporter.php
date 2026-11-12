<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Export;

use League\Flysystem\FilesystemOperator;
use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Http\PdfDownloadHeaders;
use Nowo\WordToPdfBundle\Http\PdfResponseFactory;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function copy;
use function fclose;
use function file_get_contents;
use function filesize;
use function fopen;
use function fpassthru;
use function is_resource;
use function is_string;
use function sprintf;
use function stream_copy_to_stream;
use function trigger_deprecation;
use function trim;

/**
 * Default {@see PdfWriterInterface} (and the deprecated {@see ExporterInterface}).
 *
 * {@code league/flysystem} is optional (suggest): it is only needed for {@see toFlysystem()}; the other
 * exporters never touch Flysystem classes.
 *
 * {@see toFlysystem()} writes to the profile's {@code export.flysystem_adapter} service (resolved through
 * {@code $flysystemAdapters}, built from the bundle configuration) or, when the profile sets none, to the
 * injected {@code $flysystem} operator.
 */
final readonly class PdfExporter implements PdfWriterInterface, ExporterInterface
{
    /**
     * @param FilesystemOperator|null $flysystem Optional default Flysystem operator
     * @param ContainerInterface|null $flysystemAdapters Locator of the operators named by profiles' export.flysystem_adapter
     */
    public function __construct(
        private ?FilesystemOperator $flysystem = null,
        private ?ContainerInterface $flysystemAdapters = null,
    ) {
    }

    public function toBytes(ConvertedPdf $pdf): string
    {
        try {
            $bytes = @file_get_contents($pdf->path());
            if ($bytes === false) {
                throw new ExportException(sprintf('Could not read converted PDF at "%s".', $pdf->path()));
            }

            return $bytes;
        } finally {
            $pdf->dispose();
        }
    }

    public function toStream(ConvertedPdf $pdf, mixed $stream): void
    {
        try {
            if (!is_resource($stream)) {
                throw new ExportException('PdfWriterInterface::toStream() expects an open, writable stream resource.');
            }

            $source = @fopen($pdf->path(), 'r');
            if ($source === false) {
                throw new ExportException(sprintf('Could not open PDF at "%s".', $pdf->path()));
            }
            try {
                $copied = @stream_copy_to_stream($source, $stream);
                if ($copied === false || $copied !== filesize($pdf->path())) {
                    throw new ExportException('Could not copy the PDF into the target stream.');
                }
            } finally {
                fclose($source);
            }
        } finally {
            $pdf->dispose();
        }
    }

    /**
     * Stream the PDF as an HTTP response.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     *
     * @return StreamedResponse
     *
     * @deprecated since 1.7.0, use {@see PdfResponseFactory::streamed()}
     */
    public function toStreamResponse(ConvertedPdf $pdf): StreamedResponse
    {
        trigger_deprecation('nowo-tech/word-to-pdf-bundle', '1.7', 'Calling "%s()" is deprecated, use "%s::streamed()" instead.', __METHOD__, PdfResponseFactory::class);

        $filename = $pdf->suggestedFilename();
        $path     = $pdf->path();

        return new StreamedResponse(
            static function () use ($path, $pdf): void {
                try {
                    $stream = fopen($path, 'r');
                    // @codeCoverageIgnoreStart
                    if ($stream === false) {
                        throw new ExportException(sprintf('Could not open PDF at "%s".', $path));
                    }
                    // @codeCoverageIgnoreEnd
                    try {
                        fpassthru($stream);
                    } finally {
                        fclose($stream);
                    }
                } finally {
                    $pdf->dispose();
                }
            },
            200,
            PdfDownloadHeaders::for($filename),
        );
    }

    /**
     * Return a BinaryFileResponse for download.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     *
     * @return BinaryFileResponse
     *
     * @deprecated since 1.7.0, use {@see PdfResponseFactory::binaryFile()}
     */
    public function toBinaryResponse(ConvertedPdf $pdf): BinaryFileResponse
    {
        trigger_deprecation('nowo-tech/word-to-pdf-bundle', '1.7', 'Calling "%s()" is deprecated, use "%s::binaryFile()" instead.', __METHOD__, PdfResponseFactory::class);

        $filename = $pdf->suggestedFilename();
        $response = new BinaryFileResponse($pdf->path());
        $response->headers->set('Content-Type', PdfDownloadHeaders::CONTENT_TYPE);
        $response->headers->set('Content-Disposition', PdfDownloadHeaders::contentDisposition($filename));
        if ($pdf->isTemporary()) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    /**
     * Copy the PDF to a local filesystem path.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     * @param string $path Destination path
     *
     * @return void
     */
    public function toFile(ConvertedPdf $pdf, string $path): void
    {
        try {
            if (!@copy($pdf->path(), $path)) {
                throw new ExportException(sprintf('Failed to copy PDF to "%s".', $path));
            }
            // @codeCoverageIgnoreStart
        } catch (Throwable $e) {
            if (!$e instanceof ExportException) {
                throw new ExportException(sprintf('Failed to save PDF to "%s": %s', $path, $e->getMessage()), 0, $e);
            }
            throw $e;
            // @codeCoverageIgnoreEnd
        } finally {
            $pdf->dispose();
        }
    }

    /**
     * Upload the PDF via the configured Flysystem operator.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     * @param string $remotePath Remote object path
     *
     * @return void
     */
    public function toFlysystem(ConvertedPdf $pdf, string $remotePath): void
    {
        try {
            // Inside the try: a missing or invalid adapter still disposes the converted PDF.
            $filesystem = $this->resolveFilesystem($pdf);
            $stream     = fopen($pdf->path(), 'r');
            // @codeCoverageIgnoreStart
            if ($stream === false) {
                throw new ExportException(sprintf('Could not open PDF at "%s" for Flysystem upload.', $pdf->path()));
            }
            // @codeCoverageIgnoreEnd
            try {
                $filesystem->writeStream($remotePath, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            $pdf->dispose();
        }
    }

    private function resolveFilesystem(ConvertedPdf $pdf): FilesystemOperator
    {
        $id = $pdf->flysystemAdapter();
        if (!is_string($id) || trim($id) === '') {
            if (!$this->flysystem instanceof FilesystemOperator) {
                throw new ExportException('Flysystem adapter is not configured for WordToPdfBundle exporter: install league/flysystem and set export.flysystem_adapter on the profile (or inject $flysystem).');
            }

            return $this->flysystem;
        }

        $id = trim($id);
        if (!$this->flysystemAdapters instanceof ContainerInterface || !$this->flysystemAdapters->has($id)) {
            throw new ExportException(sprintf('Flysystem adapter "%s" is not available: declare it as export.flysystem_adapter in a nowo_word_to_pdf profile.', $id));
        }

        $filesystem = $this->flysystemAdapters->get($id);
        if (!$filesystem instanceof FilesystemOperator) {
            throw new ExportException(sprintf('Flysystem adapter "%s" must implement League\\Flysystem\\FilesystemOperator (is league/flysystem installed?).', $id));
        }

        return $filesystem;
    }
}
