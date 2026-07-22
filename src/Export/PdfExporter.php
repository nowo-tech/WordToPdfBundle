<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Export;

use League\Flysystem\FilesystemOperator;
use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function copy;
use function fclose;
use function fopen;
use function fpassthru;
use function sprintf;

final readonly class PdfExporter implements ExporterInterface
{
    public function __construct(
        private ?FilesystemOperator $flysystem = null,
    ) {
    }

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

    public function toBinaryResponse(ConvertedPdf $pdf): BinaryFileResponse
    {
        $filename = $pdf->suggestedFilename();
        $response = new BinaryFileResponse($pdf->path());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        if ($pdf->isTemporary()) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

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

    public function toFlysystem(ConvertedPdf $pdf, string $remotePath): void
    {
        if (!$this->flysystem instanceof FilesystemOperator) {
            throw new ExportException('Flysystem adapter is not configured for WordToPdfBundle exporter.');
        }

        try {
            $stream = fopen($pdf->path(), 'r');
            // @codeCoverageIgnoreStart
            if ($stream === false) {
                throw new ExportException(sprintf('Could not open PDF at "%s" for Flysystem upload.', $pdf->path()));
            }
            // @codeCoverageIgnoreEnd
            try {
                $this->flysystem->writeStream($remotePath, $stream);
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
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . $filename . '"',
        ];
    }
}
