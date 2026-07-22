<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Converter;

use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Converter\WordToPdfConverter;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Nowo\WordToPdfBundle\Exception\UnsupportedFormatException;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeProcessRunner;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use PHPUnit\Framework\TestCase;

final class WordToPdfConverterTest extends TestCase
{
    public function testUnsupportedExtension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wtp_');
        self::assertNotFalse($path);
        $txt = $path . '.txt';
        rename($path, $txt);
        file_put_contents($txt, 'hello');

        $converter = $this->createConverter();
        try {
            $this->expectException(UnsupportedFormatException::class);
            $converter->convert($txt);
        } finally {
            @unlink($txt);
        }
    }

    public function testMissingSource(): void
    {
        $converter = $this->createConverter();
        $this->expectException(ConversionFailedException::class);
        $converter->convert('/tmp/missing-word-to-pdf.docx');
    }

    public function testConvertDelegatesWhenReady(): void
    {
        $docx = $this->makeFakeDocx();
        $out  = sys_get_temp_dir() . '/wtp_out_' . uniqid('', true) . '.pdf';
        file_put_contents($out, '%PDF-1.4');

        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->method('assertReady')->willReturn('/usr/bin/soffice');

        $runner = $this->createMock(LibreOfficeProcessRunner::class);
        $runner->expects(self::once())->method('convert')->willReturn($out);

        $converter = new WordToPdfConverter(
            new ProfileResolver(['default' => ['export' => ['filename' => 'demo.pdf']]], 'default'),
            $checker,
            $runner,
        );

        $pdf = $converter->convert($docx);
        self::assertSame('demo.pdf', $pdf->suggestedFilename());
        self::assertSame('%PDF-1.4', $pdf->readContents());
        $pdf->dispose();
        @unlink($docx);
    }

    public function testAssertRuntimeReadyPropagates(): void
    {
        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->expects(self::once())->method('assertReady')
            ->willThrowException(MissingDependencyException::libreOfficeWriterNotFound());

        $converter = new WordToPdfConverter(
            new ProfileResolver(['default' => []], 'default'),
            $checker,
            $this->createMock(LibreOfficeProcessRunner::class),
        );

        $this->expectException(MissingDependencyException::class);
        $converter->assertRuntimeReady();
    }

    public function testConvertWithProfileAndInline(): void
    {
        $docx = $this->makeFakeDocx();
        $out  = sys_get_temp_dir() . '/wtp_out_' . uniqid('', true) . '.pdf';
        file_put_contents($out, '%PDF');

        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->method('assertReady')->willReturn('/bin/soffice');
        $runner = $this->createMock(LibreOfficeProcessRunner::class);
        $runner->method('convert')->willReturn($out);

        $converter = new WordToPdfConverter(
            new ProfileResolver([
                'default' => [],
                'fast'    => ['timeout' => 5, 'export' => ['filename' => 'fast.pdf']],
            ], 'default'),
            $checker,
            $runner,
        );

        $a = $converter->convertWithProfile($docx, 'fast');
        self::assertSame('fast.pdf', $a->suggestedFilename());
        $a->dispose();

        $out2 = sys_get_temp_dir() . '/wtp_out_' . uniqid('', true) . '.pdf';
        file_put_contents($out2, '%PDF');
        $runner2 = $this->createMock(LibreOfficeProcessRunner::class);
        $runner2->method('convert')->willReturn($out2);
        $converter2 = new WordToPdfConverter(
            new ProfileResolver(['default' => []], 'default'),
            $checker,
            $runner2,
        );
        $b = $converter2->convertWithInlineProfile($docx, ['export' => ['filename' => 'inline.pdf']]);
        self::assertSame('inline.pdf', $b->suggestedFilename());
        $b->dispose();
        @unlink($docx);
    }

    public function testMaxSourceBytes(): void
    {
        $docx      = $this->makeFakeDocx();
        $converter = new WordToPdfConverter(
            new ProfileResolver(['default' => ['max_source_bytes' => 1]], 'default'),
            $this->createMock(RuntimeRequirementsChecker::class),
            $this->createMock(LibreOfficeProcessRunner::class),
        );
        try {
            $this->expectException(ConversionFailedException::class);
            $this->expectExceptionMessage('max_source_bytes');
            $converter->convert($docx);
        } finally {
            @unlink($docx);
        }
    }

    public function testMimeRejectionForTextFileWithDocxExtension(): void
    {
        $path = sys_get_temp_dir() . '/wtp_' . uniqid('', true) . '.docx';
        file_put_contents($path, "This is plain text not a zip\n");
        $converter = $this->createConverter();
        try {
            $converter->convert($path);
            self::fail('Expected an exception for non-Word content');
        } catch (UnsupportedFormatException|ConversionFailedException|MissingDependencyException $e) {
            self::assertNotSame('', $e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    public function testFilenameGetsPdfSuffix(): void
    {
        $docx = $this->makeFakeDocx();
        $out  = sys_get_temp_dir() . '/wtp_out_' . uniqid('', true) . '.pdf';
        file_put_contents($out, '%PDF');

        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->method('assertReady')->willReturn('/usr/bin/soffice');
        $runner = $this->createMock(LibreOfficeProcessRunner::class);
        $runner->method('convert')->willReturn($out);

        $converter = new WordToPdfConverter(
            new ProfileResolver(['default' => ['export' => ['filename' => 'report']]], 'default'),
            $checker,
            $runner,
        );
        $pdf = $converter->convert($docx);
        self::assertSame('report.pdf', $pdf->suggestedFilename());
        $pdf->dispose();
        @unlink($docx);
    }

    private function createConverter(): WordToPdfConverter
    {
        return new WordToPdfConverter(
            new ProfileResolver(['default' => []], 'default'),
            $this->createMock(RuntimeRequirementsChecker::class),
            $this->createMock(LibreOfficeProcessRunner::class),
        );
    }

    private function makeFakeDocx(): string
    {
        $path = sys_get_temp_dir() . '/wtp_' . uniqid('', true) . '.docx';
        // Minimal ZIP (docx is a zip) — PK header
        file_put_contents($path, "PK\x03\x04fake-docx-content-for-tests");

        return $path;
    }
}
