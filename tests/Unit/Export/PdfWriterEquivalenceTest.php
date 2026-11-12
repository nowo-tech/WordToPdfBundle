<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Export;

use League\Flysystem\FilesystemOperator;
use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Export\ExporterInterface;
use Nowo\WordToPdfBundle\Export\PdfExporter;
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Http\PdfResponseFactory;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function fwrite;
use function glob;
use function ob_get_clean;
use function ob_start;
use function random_bytes;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

/**
 * 1.7.0 equivalence: {@see PdfWriterInterface} and {@see PdfResponseFactory} produce the same bytes, status and
 * download headers as the deprecated {@see ExporterInterface} methods and dispose the
 * converted PDF the same way (temporary PDFs deleted, kept ones left in place).
 */
final class PdfWriterEquivalenceTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            @unlink($path);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function filenames(): iterable
    {
        yield 'plain' => ['report.pdf'];
        yield 'quote injection' => ['a".pdf'];
        yield 'header injection' => ["x.pdf\r\nSet-Cookie: a=b"];
        yield 'path separators' => ['../../etc/x.pdf'];
        yield 'utf-8' => ['informe ñ.pdf'];
        yield 'empty' => ["\r\n"];
    }

    #[DataProvider('filenames')]
    #[IgnoreDeprecations]
    public function testStreamedMatchesDeprecatedStreamResponse(string $filename): void
    {
        $exporter = new PdfExporter();
        $bytes    = '%PDF-1.4 ' . random_bytes(2048);

        $old = $exporter->toStreamResponse($this->pdf($bytes, true, $filename, $oldPath));
        $new = (new PdfResponseFactory($exporter))->streamed($this->pdf($bytes, true, $filename, $newPath));

        self::assertSame($this->headers($old), $this->headers($new));
        $body = $this->send($new);
        self::assertSame($this->send($old), $body);
        self::assertSame($bytes, $body);
        self::assertFileDoesNotExist($oldPath);
        self::assertFileDoesNotExist($newPath);
    }

    #[DataProvider('filenames')]
    #[IgnoreDeprecations]
    public function testBinaryFileMatchesDeprecatedBinaryResponse(string $filename): void
    {
        $exporter = new PdfExporter();
        $bytes    = '%PDF-1.4 ' . random_bytes(2048);

        $old             = $exporter->toBinaryResponse($this->pdf($bytes, true, $filename));
        $new             = (new PdfResponseFactory($exporter))->binaryFile($this->pdf($bytes, true, $filename, $source));
        $this->cleanup[] = $old->getFile()->getPathname();
        $this->cleanup[] = $new->getFile()->getPathname();

        self::assertSame($this->headers($old), $this->headers($new));
        self::assertSame(file_get_contents($old->getFile()->getPathname()), file_get_contents($new->getFile()->getPathname()));
        self::assertTrue($this->deletesAfterSend($old));
        self::assertTrue($this->deletesAfterSend($new));
        // The converted PDF is disposed once it is copied to the download file.
        self::assertFileDoesNotExist($source);
    }

    public function testKeptPdfIsNotDeletedByTheNewPaths(): void
    {
        $exporter = new PdfExporter();
        $factory  = new PdfResponseFactory($exporter);
        $kept     = $this->pdf('%PDF-kept', false, 'k.pdf', $path);

        self::assertSame('%PDF-kept', $exporter->toBytes($kept));
        self::assertSame('%PDF-kept', $this->send($factory->streamed($kept)));
        $binary          = $factory->binaryFile($kept);
        $this->cleanup[] = $binary->getFile()->getPathname();
        self::assertTrue($this->deletesAfterSend($binary));
        self::assertNotSame($path, $binary->getFile()->getPathname());
        self::assertFileExists($path);
    }

    public function testBytesAndStreamMatchFile(): void
    {
        $exporter = new PdfExporter();
        $bytes    = '%PDF-1.4 ' . random_bytes(4096);

        $target          = sys_get_temp_dir() . '/wtp_eq_' . uniqid('', true) . '.pdf';
        $this->cleanup[] = $target;
        $exporter->toFile($this->pdf($bytes, true, 'a.pdf', $fileSource), $target);
        self::assertSame($bytes, file_get_contents($target));
        self::assertFileDoesNotExist($fileSource);

        self::assertSame($bytes, $exporter->toBytes($this->pdf($bytes, true, 'a.pdf', $bytesSource)));
        self::assertFileDoesNotExist($bytesSource);

        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, 'prefix:');
        $exporter->toStream($this->pdf($bytes, true, 'a.pdf', $streamSource), $stream);
        rewind($stream);
        self::assertSame('prefix:' . $bytes, stream_get_contents($stream));
        fclose($stream);
        self::assertFileDoesNotExist($streamSource);
    }

    public function testErrorsDisposeThePdf(): void
    {
        $exporter = new PdfExporter();

        foreach ([
            'non-stream' => static function (ConvertedPdf $p) use ($exporter): void {
                $exporter->toStream($p, 'not-a-stream'); // @phpstan-ignore argument.type (the contract violation under test)
            },
            'read-only' => function (ConvertedPdf $p) use ($exporter): void {
                $path            = (string) tempnam(sys_get_temp_dir(), 'wtp_ro_');
                $this->cleanup[] = $path;
                $readOnly        = fopen($path, 'r');
                self::assertIsResource($readOnly);
                try {
                    $exporter->toStream($p, $readOnly);
                } finally {
                    fclose($readOnly);
                }
            },
        ] as $name => $run) {
            $pdf = $this->pdf('%PDF-x', true, 'e.pdf', $path);
            try {
                $run($pdf);
                self::fail('Expected ExportException: ' . $name);
            } catch (ExportException) {
            }
            self::assertFileDoesNotExist($path, $name);
        }
    }

    public function testMissingPdfFailsWithExportException(): void
    {
        $exporter = new PdfExporter();
        $missing  = new ConvertedPdf(sys_get_temp_dir() . '/wtp_missing_' . uniqid('', true) . '.pdf', true);
        $before   = glob(sys_get_temp_dir() . '/word_to_pdf_dl_*') ?: [];

        foreach ([
            static fn (): mixed => $exporter->toBytes($missing),
            static function () use ($exporter, $missing): void {
                $stream = fopen('php://memory', 'w+');
                self::assertIsResource($stream);
                $exporter->toStream($missing, $stream);
            },
            static fn (): mixed => (new PdfResponseFactory($exporter))->binaryFile($missing),
        ] as $run) {
            try {
                $run();
                self::fail('Expected ExportException.');
            } catch (ExportException) {
            }
        }

        self::assertSame($before, glob(sys_get_temp_dir() . '/word_to_pdf_dl_*') ?: []);
    }

    /** Audit MW-W-L1: a missing or unknown Flysystem adapter still disposes the temporary PDF. */
    public function testMissingFlysystemAdapterDisposesThePdf(): void
    {
        $missing = $this->pdf('%PDF', true, 'm.pdf', $path);
        try {
            (new PdfExporter())->toFlysystem($missing, 'remote/m.pdf');
            self::fail('Expected ExportException.');
        } catch (ExportException $e) {
            self::assertStringContainsString('Flysystem adapter is not configured', $e->getMessage());
        }
        self::assertFileDoesNotExist($path);

        $unknown = new ConvertedPdf($this->pdfPath('%PDF'), true, 'u.pdf', 'app.unknown_storage');
        try {
            (new PdfExporter())->toFlysystem($unknown, 'remote/u.pdf');
            self::fail('Expected ExportException.');
        } catch (ExportException $e) {
            self::assertStringContainsString('app.unknown_storage', $e->getMessage());
        }
        self::assertFileDoesNotExist($unknown->path());
    }

    public function testFlysystemThroughThePort(): void
    {
        $fs = $this->createMock(FilesystemOperator::class);
        $fs->expects(self::once())->method('writeStream')->with('remote/x.pdf', self::isResource());

        $writer = $this->writer(new PdfExporter($fs));
        $writer->toFlysystem($this->pdf('%PDF', true, 'x.pdf', $path), 'remote/x.pdf');
        self::assertFileDoesNotExist($path);
    }

    private function writer(PdfWriterInterface $writer): PdfWriterInterface
    {
        return $writer;
    }

    /**
     * @param-out string $path
     */
    private function pdf(string $bytes, bool $temporary, string $filename, ?string &$path = null): ConvertedPdf
    {
        $path            = (string) tempnam(sys_get_temp_dir(), 'wtp_src_');
        $this->cleanup[] = $path;
        file_put_contents($path, $bytes);

        return new ConvertedPdf($path, $temporary, $filename);
    }

    private function pdfPath(string $bytes): string
    {
        $path            = (string) tempnam(sys_get_temp_dir(), 'wtp_src_');
        $this->cleanup[] = $path;
        file_put_contents($path, $bytes);

        return $path;
    }

    private function send(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, string|null>
     */
    private function headers(Response $response): array
    {
        return [
            'status'              => (string) $response->getStatusCode(),
            'Content-Type'        => $response->headers->get('Content-Type'),
            'Content-Disposition' => $response->headers->get('Content-Disposition'),
        ];
    }

    private function deletesAfterSend(BinaryFileResponse $response): bool
    {
        return (bool) (new ReflectionProperty($response, 'deleteFileAfterSend'))->getValue($response);
    }
}
