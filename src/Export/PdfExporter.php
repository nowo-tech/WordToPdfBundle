<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Export;

use League\Flysystem\FilesystemOperator;
use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function copy;
use function fclose;
use function fopen;
use function fpassthru;
use function is_string;
use function preg_replace;
use function sprintf;
use function trim;

/**
 * {@see toFlysystem()} writes to the profile's {@code export.flysystem_adapter} service (resolved through
 * {@code $flysystemAdapters}, built from the bundle configuration) or, when the profile sets none, to the
 * injected {@code $flysystem} operator.
 */
final readonly class PdfExporter implements ExporterInterface
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

    /**
     * Stream the PDF as an HTTP response.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     *
     * @return StreamedResponse
     */
    public function toStreamResponse(ConvertedPdf $pdf): StreamedResponse
    {
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
            $this->responseHeaders($filename),
        );
    }

    /**
     * Return a BinaryFileResponse for download.
     *
     * @param ConvertedPdf $pdf Converted PDF handle
     *
     * @return BinaryFileResponse
     */
    public function toBinaryResponse(ConvertedPdf $pdf): BinaryFileResponse
    {
        $filename = $pdf->suggestedFilename();
        $response = new BinaryFileResponse($pdf->path());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', self::contentDisposition($filename));
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
        $filesystem = $this->resolveFilesystem($pdf);

        try {
            $stream = fopen($pdf->path(), 'r');
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

    /**
     * @return array<string, string>
     */
    private function responseHeaders(string $filename): array
    {
        return [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => self::contentDisposition($filename),
        ];
    }

    /**
     * RFC 6266 attachment header (quoted ASCII fallback + filename* UTF-8) via {@see HeaderUtils::makeDisposition()},
     * so quotes, CR/LF, path separators or non-ASCII characters in export.filename cannot break or inject headers.
     */
    private static function contentDisposition(string $filename): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $filename));
        if (trim($filename, ' _.') === '') {
            $filename = 'document.pdf';
        }
        $fallback = (string) preg_replace('/[^\x20-\x7E]|[%"]/u', '_', $filename);

        return HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, $fallback);
    }

    private function resolveFilesystem(ConvertedPdf $pdf): FilesystemOperator
    {
        $id = $pdf->flysystemAdapter();
        if (!is_string($id) || trim($id) === '') {
            if (!$this->flysystem instanceof FilesystemOperator) {
                throw new ExportException('Flysystem adapter is not configured for WordToPdfBundle exporter: set export.flysystem_adapter on the profile (or inject $flysystem).');
            }

            return $this->flysystem;
        }

        $id = trim($id);
        if (!$this->flysystemAdapters instanceof ContainerInterface || !$this->flysystemAdapters->has($id)) {
            throw new ExportException(sprintf('Flysystem adapter "%s" is not available: declare it as export.flysystem_adapter in a nowo_word_to_pdf profile.', $id));
        }

        $filesystem = $this->flysystemAdapters->get($id);
        if (!$filesystem instanceof FilesystemOperator) {
            throw new ExportException(sprintf('Flysystem adapter "%s" must implement League\\Flysystem\\FilesystemOperator.', $id));
        }

        return $filesystem;
    }
}
