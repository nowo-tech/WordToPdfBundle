<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Export;

use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Export\PdfExporter;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use PHPUnit\Framework\TestCase;

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

        $fs = $this->createMock(\League\Flysystem\FilesystemOperator::class);
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
}
