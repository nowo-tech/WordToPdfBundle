<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Export;

use League\Flysystem\FilesystemOperator;
use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Export\PdfExporter;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class PdfExporterTest extends TestCase
{
    public function testToFileAndBinaryResponse(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);
        file_put_contents($src, '%PDF-1.4');

        $exporter = new PdfExporter();
        $dest     = sys_get_temp_dir() . '/wtp_copy_' . uniqid('', true) . '.pdf';
        // toFile disposes temporary source; use non-temporary so we can assert contents after
        $pdfKeep = new ConvertedPdf($src, false, 'doc.pdf');
        $exporter->toFile($pdfKeep, $dest);
        self::assertFileExists($dest);
        self::assertSame('%PDF-1.4', file_get_contents($dest));
        @unlink($dest);

        $src2 = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src2);
        file_put_contents($src2, '%PDF');
        $response = $exporter->toBinaryResponse(new ConvertedPdf($src2, true, 'x.pdf'));
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        // BinaryFileResponse with deleteFileAfterSend — file still exists until send
        self::assertFileExists($src2);
        @unlink($src2);
        @unlink($src);
    }

    public function testFlysystemRequiresAdapter(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);
        file_put_contents($src, '%PDF');
        $exporter = new PdfExporter();
        $this->expectException(ExportException::class);
        try {
            $exporter->toFlysystem(new ConvertedPdf($src, false), 'remote.pdf');
        } finally {
            @unlink($src);
        }
    }

    public function testStreamResponse(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);
        file_put_contents($src, '%PDF-stream');
        $exporter = new PdfExporter();
        $response = $exporter->toStreamResponse(new ConvertedPdf($src, true, 's.pdf'));
        ob_start();
        $response->sendContent();
        $body = ob_get_clean();
        self::assertSame('%PDF-stream', $body);
    }

    public function testFlysystemUpload(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);
        file_put_contents($src, '%PDF');

        $fs = $this->createMock(FilesystemOperator::class);
        $fs->expects(self::once())->method('writeStream');

        $exporter = new PdfExporter($fs);
        $exporter->toFlysystem(new ConvertedPdf($src, true, 'x.pdf'), 'remote/x.pdf');
    }

    public function testToFileFailure(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);
        file_put_contents($src, '%PDF');
        $exporter = new PdfExporter();
        $this->expectException(ExportException::class);
        $exporter->toFile(new ConvertedPdf($src, false), '/proc/does-not-allow-write-' . uniqid() . '/x.pdf');
    }

    public function testBinaryResponseNonTemporary(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);
        file_put_contents($src, '%PDF');
        $exporter = new PdfExporter();
        $response = $exporter->toBinaryResponse(new ConvertedPdf($src, false, 'n.pdf'));
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        @unlink($src);
    }

    public function testContentDispositionIsSafeForHostileAndUnicodeNames(): void
    {
        $exporter = new PdfExporter();

        foreach (['stream', 'binary'] as $mode) {
            $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
            self::assertNotFalse($src);
            file_put_contents($src, '%PDF');
            $pdf      = new ConvertedPdf($src, false, "inf\"orme\r\nX-Evil: 1/ñ.pdf");
            $response = $mode === 'stream' ? $exporter->toStreamResponse($pdf) : $exporter->toBinaryResponse($pdf);
            $header   = (string) $response->headers->get('Content-Disposition');
            @unlink($src);

            self::assertStringNotContainsString("\r", $header);
            self::assertStringNotContainsString("\n", $header);
            self::assertStringStartsWith('attachment; filename="inf_orme_X-Evil: 1__.pdf"', $header);
            self::assertStringContainsString("filename*=utf-8''", $header);
        }
    }

    public function testContentDispositionFallsBackForEmptyName(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);
        $response = (new PdfExporter())->toStreamResponse(new ConvertedPdf($src, false, " /\n "));
        @unlink($src);

        self::assertSame('attachment; filename=document.pdf', $response->headers->get('Content-Disposition'));
    }

    public function testProfileAdapterIsResolvedFromLocator(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);
        file_put_contents($src, '%PDF');

        $fallback = $this->createMock(FilesystemOperator::class);
        $fallback->expects(self::never())->method('writeStream');
        $profileFs = $this->createMock(FilesystemOperator::class);
        $profileFs->expects(self::once())->method('writeStream')->with('remote/a.pdf', self::isType('resource'));

        $exporter = new PdfExporter($fallback, new ServiceLocator([
            'app.storage' => static fn (): FilesystemOperator => $profileFs,
        ]));
        $exporter->toFlysystem(new ConvertedPdf($src, true, 'a.pdf', 'app.storage'), 'remote/a.pdf');
        self::assertFileDoesNotExist($src);
    }

    public function testUnknownProfileAdapterThrowsAndKeepsPdf(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'wtp_pdf_');
        self::assertNotFalse($src);

        try {
            (new PdfExporter(null, new ServiceLocator([])))->toFlysystem(new ConvertedPdf($src, true, 'a.pdf', 'app.missing'), 'r.pdf');
            self::fail('Expected ExportException');
        } catch (ExportException $e) {
            self::assertStringContainsString('Flysystem adapter "app.missing" is not available', $e->getMessage());
            self::assertFileExists($src);
        } finally {
            @unlink($src);
        }
    }

    public function testProfileAdapterWithoutLocatorThrows(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('is not available');
        (new PdfExporter())->toFlysystem(new ConvertedPdf('/nonexistent.pdf', false, 'a.pdf', 'app.storage'), 'r.pdf');
    }

    public function testProfileAdapterOfWrongTypeThrows(): void
    {
        $exporter = new PdfExporter(null, new ServiceLocator([
            'app.storage' => static fn (): stdClass => new stdClass(),
        ]));

        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('must implement League\Flysystem\FilesystemOperator');
        $exporter->toFlysystem(new ConvertedPdf('/nonexistent.pdf', false, 'a.pdf', 'app.storage'), 'r.pdf');
    }
}
