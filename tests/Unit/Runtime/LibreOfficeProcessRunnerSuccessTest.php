<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Runtime;

use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeBinaryLocator;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeProcessRunner;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use PHPUnit\Framework\TestCase;

use function dirname;

final class LibreOfficeProcessRunnerSuccessTest extends TestCase
{
    private string $fakeSoffice;

    protected function setUp(): void
    {
        $this->fakeSoffice = dirname(__DIR__, 2) . '/Fixtures/fake-soffice.sh';
        self::assertFileExists($this->fakeSoffice);
        chmod($this->fakeSoffice, 0755);
    }

    public function testSuccessfulConversionWithFakeBinary(): void
    {
        $src = sys_get_temp_dir() . '/wtp_' . uniqid('', true) . '.docx';
        file_put_contents($src, "PK\x03\x04content");

        $runner = new LibreOfficeProcessRunner();
        $config = ResolvedConfig::fromArray([
            'temp_dir' => sys_get_temp_dir(),
            'timeout'  => 10,
            'filter'   => 'pdf:writer_pdf_Export',
        ]);

        $pdfPath = $runner->convert($src, $this->fakeSoffice, $config);
        try {
            self::assertFileExists($pdfPath);
            self::assertStringStartsWith('%PDF', (string) file_get_contents($pdfPath));
        } finally {
            @unlink($pdfPath);
            @unlink($src);
        }
    }

    public function testCheckerWithFakeBinaryAndMinVersion(): void
    {
        $checker = new RuntimeRequirementsChecker(new LibreOfficeBinaryLocator());
        $config  = ResolvedConfig::fromArray([
            'binary_path' => $this->fakeSoffice,
            'temp_dir'    => sys_get_temp_dir(),
            'min_version' => '24.0',
        ]);
        $binary = $checker->assertReady($config);
        self::assertSame($this->fakeSoffice, $binary);

        $diag = $checker->diagnose($config);
        self::assertTrue($diag['ok']);
        self::assertNotNull($diag['version']);
        self::assertTrue($checker->isReady($config));
    }

    public function testCheckerVersionTooOld(): void
    {
        $checker = new RuntimeRequirementsChecker(new LibreOfficeBinaryLocator());
        $config  = ResolvedConfig::fromArray([
            'binary_path' => $this->fakeSoffice,
            'temp_dir'    => sys_get_temp_dir(),
            'min_version' => '99.0',
        ]);
        $this->expectException(MissingDependencyException::class);
        $this->expectExceptionMessage('below the required minimum');
        $checker->assertReady($config);
    }

    public function testNoPdfProduced(): void
    {
        $fake = dirname(__DIR__, 2) . '/Fixtures/fake-soffice-nopdf.sh';
        chmod($fake, 0755);
        $src = sys_get_temp_dir() . '/wtp_' . uniqid('', true) . '.docx';
        file_put_contents($src, "PK\x03\x04");

        $runner = new LibreOfficeProcessRunner();
        $config = ResolvedConfig::fromArray(['temp_dir' => sys_get_temp_dir(), 'timeout' => 5]);
        try {
            $this->expectException(ConversionFailedException::class);
            $this->expectExceptionMessage('did not produce');
            $runner->convert($src, $fake, $config);
        } finally {
            @unlink($src);
        }
    }

    public function testVersionUnreadableWhenNoNumber(): void
    {
        $fake = dirname(__DIR__, 2) . '/Fixtures/fake-soffice-nover.sh';
        chmod($fake, 0755);
        $checker = new RuntimeRequirementsChecker(new LibreOfficeBinaryLocator());
        $config  = ResolvedConfig::fromArray([
            'binary_path' => $fake,
            'temp_dir'    => sys_get_temp_dir(),
            'min_version' => '1.0',
        ]);
        $this->expectException(MissingDependencyException::class);
        $this->expectExceptionMessage('Could not read LibreOffice version');
        $checker->assertReady($config);
    }

    public function testVersionProcessFailure(): void
    {
        $fake = dirname(__DIR__, 2) . '/Fixtures/fake-soffice-failver.sh';
        chmod($fake, 0755);
        $checker = new RuntimeRequirementsChecker(new LibreOfficeBinaryLocator());
        $config  = ResolvedConfig::fromArray([
            'binary_path' => $fake,
            'temp_dir'    => sys_get_temp_dir(),
            'min_version' => '1.0',
        ]);
        $this->expectException(MissingDependencyException::class);
        $checker->assertReady($config);
    }

    public function testTimeoutPathWithSleepBinary(): void
    {
        if (!is_executable('/bin/sleep')) {
            self::markTestSkipped('/bin/sleep not available');
        }

        $wrapper = sys_get_temp_dir() . '/wtp_sleep_' . uniqid('', true) . '.sh';
        file_put_contents($wrapper, "#!/bin/sh\nsleep 30\n");
        chmod($wrapper, 0755);

        $src = sys_get_temp_dir() . '/wtp_' . uniqid('', true) . '.docx';
        file_put_contents($src, 'x');

        $runner = new LibreOfficeProcessRunner();
        $config = ResolvedConfig::fromArray([
            'temp_dir' => sys_get_temp_dir(),
            'timeout'  => 1,
        ]);

        try {
            $this->expectException(ConversionFailedException::class);
            $this->expectExceptionMessage('timed out');
            $runner->convert($src, $wrapper, $config);
        } finally {
            @unlink($src);
            @unlink($wrapper);
        }
    }
}
