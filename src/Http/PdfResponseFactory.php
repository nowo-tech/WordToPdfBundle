<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Http;

use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;
use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Export\ExporterInterface;
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function fclose;
use function fopen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * HTTP edge for PDF downloads (REQ-ARCH-001 rule 3): turns a {@see ConvertedPdf} into a Symfony response by writing it
 * through the framework-free {@see PdfWriterInterface}. Use it in controllers; application and domain code depend on
 * {@see WordToPdfConverterInterface} and {@see PdfWriterInterface} only.
 *
 * When the host aliases or decorates {@see PdfWriterInterface}, this factory uses the host service.
 *
 * Since 1.7.0; replaces {@see ExporterInterface::toStreamResponse()} and
 * {@see ExporterInterface::toBinaryResponse()}.
 */
final readonly class PdfResponseFactory
{
    public function __construct(
        private PdfWriterInterface $writer,
    ) {
    }

    /**
     * Streams the PDF to the client (it is written, then disposed, when the response is sent).
     */
    public function streamed(ConvertedPdf $pdf): StreamedResponse
    {
        $writer = $this->writer;

        return new StreamedResponse(
            static function () use ($writer, $pdf): void {
                $output = fopen('php://output', 'w');
                // @codeCoverageIgnoreStart
                if ($output === false) {
                    $pdf->dispose();

                    throw new ExportException('Could not open the output stream for the PDF download.');
                }
                // @codeCoverageIgnoreEnd
                try {
                    $writer->toStream($pdf, $output);
                } finally {
                    fclose($output);
                }
            },
            200,
            PdfDownloadHeaders::for($pdf->suggestedFilename()),
        );
    }

    /**
     * Writes the PDF to a temporary download file now (the converted PDF is disposed) and returns a response that
     * sends it and deletes it afterwards.
     *
     * @throws ExportException when the PDF cannot be written
     */
    public function binaryFile(ConvertedPdf $pdf): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'word_to_pdf_dl_');
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            $pdf->dispose();

            throw new ExportException('Could not create a temporary file for the PDF download.');
        }
        // @codeCoverageIgnoreEnd

        try {
            $this->writer->toFile($pdf, $tmp);
        } catch (Throwable $e) {
            @unlink($tmp);

            throw $e;
        }

        $response = new BinaryFileResponse($tmp, 200, PdfDownloadHeaders::for($pdf->suggestedFilename()));
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
