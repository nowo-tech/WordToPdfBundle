<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Runtime;

use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeBinaryLocator;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use PHPUnit\Framework\TestCase;

use function dirname;

final class RuntimeRequirementsCheckerVersionTest extends TestCase
{
    private string $dir;

    private string $binary;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wtp_ver_' . uniqid('', true);
        mkdir($this->dir, 0700, true);
        $this->binary = $this->dir . '/soffice';
        copy(dirname(__DIR__, 2) . '/Fixtures/fake-soffice-recordver.sh', $this->binary);
        chmod($this->binary, 0755);
    }

    protected function tearDown(): void
    {
        foreach (['args.log', 'fail', 'soffice'] as $f) {
            @unlink($this->dir . '/' . $f);
        }
        @rmdir($this->dir);
    }

    public function testVersionRunsWithIsolatedProfileAndIsMemoizedPerBinary(): void
    {
        $checker = new RuntimeRequirementsChecker(new LibreOfficeBinaryLocator());
        $config  = $this->config($this->binary);

        self::assertSame($this->binary, $checker->assertReady($config));
        $checker->assertReady($config);
        self::assertSame('24.8.1', $checker->diagnose($config)['version']);

        $calls = $this->calls();
        self::assertCount(1, $calls);
        self::assertSame(1, preg_match('#^-env:UserInstallation=file://(\S+/word_to_pdf_version_\S+) --version$#', $calls[0], $m));
        self::assertDirectoryDoesNotExist($m[1], 'temporary LibreOffice profile must be removed');
    }

    public function testFailedVersionReadIsNotMemoized(): void
    {
        $checker = new RuntimeRequirementsChecker(new LibreOfficeBinaryLocator());
        $config  = $this->config($this->binary);

        touch($this->dir . '/fail');
        self::assertFalse($checker->isReady($config));

        unlink($this->dir . '/fail');
        self::assertTrue($checker->isReady($config));
        self::assertTrue($checker->isReady($config));

        self::assertCount(2, $this->calls());
    }

    public function testResetClearsMemoizedVersions(): void
    {
        $checker = new RuntimeRequirementsChecker(new LibreOfficeBinaryLocator());
        $config  = $this->config($this->binary);

        $checker->assertReady($config);
        $checker->reset();
        $checker->assertReady($config);

        self::assertCount(2, $this->calls());
    }

    public function testVersionTimeoutIsReportedAsUnreadableVersion(): void
    {
        $slow = dirname(__DIR__, 2) . '/Fixtures/slow-soffice.sh';
        chmod($slow, 0755);
        $checker = new RuntimeRequirementsChecker(new LibreOfficeBinaryLocator(), 0.3);

        $this->expectException(MissingDependencyException::class);
        $this->expectExceptionMessage('Could not read LibreOffice version');
        $checker->assertReady($this->config($slow));
    }

    private function config(string $binary): ResolvedConfig
    {
        return ResolvedConfig::fromArray([
            'binary_path' => $binary,
            'temp_dir'    => sys_get_temp_dir(),
            'min_version' => '24.0',
        ]);
    }

    /**
     * @return list<string>
     */
    private function calls(): array
    {
        $log     = $this->dir . '/args.log';
        $content = is_file($log) ? (string) file_get_contents($log) : '';

        return array_values(array_filter(explode("\n", $content), static fn (string $l): bool => $l !== ''));
    }
}
