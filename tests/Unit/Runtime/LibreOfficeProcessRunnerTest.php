<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Runtime;

use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeProcessRunner;
use PHPUnit\Framework\TestCase;

use function dirname;

final class LibreOfficeProcessRunnerTest extends TestCase
{
    public function testFailsWithFakeBinary(): void
    {
        $src = sys_get_temp_dir() . '/wtp_' . uniqid('', true) . '.docx';
        file_put_contents($src, "PK\x03\x04");

        $runner = new LibreOfficeProcessRunner();
        $config = ResolvedConfig::fromArray([
            'temp_dir' => sys_get_temp_dir(),
            'timeout'  => 2,
            'filter'   => 'pdf:writer_pdf_Export',
        ]);

        try {
            $this->expectException(ConversionFailedException::class);
            $runner->convert($src, '/bin/false', $config);
        } finally {
            @unlink($src);
        }
    }

    public function testTimeoutPathWithSleepBinary(): void
    {
        $binary = dirname(__DIR__, 2) . '/Fixtures/slow-soffice.sh';
        if (!is_file($binary)) {
            self::markTestSkipped('slow-soffice.sh fixture is missing');
        }
        @chmod($binary, 0755);
        if (!is_executable($binary)) {
            self::markTestSkipped('slow-soffice.sh fixture is not executable');
        }

        $src = sys_get_temp_dir() . '/wtp_timeout_' . uniqid('', true) . '.docx';
        file_put_contents($src, "PK\x03\x04");

        $runner = new LibreOfficeProcessRunner();
        $config = ResolvedConfig::fromArray([
            'temp_dir' => sys_get_temp_dir(),
            'timeout'  => 1,
            'filter'   => 'pdf:writer_pdf_Export',
        ]);

        try {
            $this->expectException(ConversionFailedException::class);
            $this->expectExceptionMessageMatches('/timed out/i');
            $runner->convert($src, $binary, $config);
        } finally {
            @unlink($src);
        }
    }
}
