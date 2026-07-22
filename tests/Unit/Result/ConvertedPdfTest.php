<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Result;

use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConvertedPdfTest extends TestCase
{
    public function testReadAndDisposeTemporary(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wtp_');
        self::assertNotFalse($path);
        file_put_contents($path, '%PDF-1.4 test');

        $pdf = new ConvertedPdf($path, true, 'out.pdf');
        self::assertSame($path, $pdf->path());
        self::assertTrue($pdf->isTemporary());
        self::assertSame('out.pdf', $pdf->suggestedFilename());
        self::assertSame('%PDF-1.4 test', $pdf->readContents());
        $pdf->dispose();
        self::assertFileDoesNotExist($path);
    }

    public function testDisposeNonTemporaryKeepsFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wtp_');
        self::assertNotFalse($path);
        file_put_contents($path, 'x');
        $pdf = new ConvertedPdf($path, false);
        $pdf->dispose();
        self::assertFileExists($path);
        @unlink($path);
    }

    public function testReadMissingThrows(): void
    {
        $pdf = new ConvertedPdf('/tmp/does-not-exist-word-to-pdf.pdf', false);
        $this->expectException(RuntimeException::class);
        $pdf->readContents();
    }
}
