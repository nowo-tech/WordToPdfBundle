<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Export;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use const PHP_BINARY;

/**
 * league/flysystem is only suggested: loading the bundle and exporting without it must work, and
 * toFlysystem() must fail with a clear ExportException instead of a fatal "class not found".
 */
#[CoversNothing]
final class PdfExporterWithoutFlysystemTest extends TestCase
{
    public function testBundleAndExporterWorkWhenFlysystemIsNotInstalled(): void
    {
        $pdf = tempnam(sys_get_temp_dir(), 'wtp_nofs_');
        self::assertIsString($pdf);
        file_put_contents($pdf, '%PDF-1.4');

        try {
            $process = new Process([
                PHP_BINARY,
                __DIR__ . '/../../Fixtures/without-flysystem.php',
                __DIR__ . '/../../../vendor/autoload.php',
                $pdf,
            ]);
            $process->run();

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
            self::assertStringContainsString('install league/flysystem', $process->getOutput());
        } finally {
            @unlink($pdf);
        }
    }
}
