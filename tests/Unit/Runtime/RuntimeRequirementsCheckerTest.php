<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Runtime;

use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeBinaryLocator;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use PHPUnit\Framework\TestCase;

final class RuntimeRequirementsCheckerTest extends TestCase
{
    public function testMissingBinaryThrowsWithInstallHint(): void
    {
        $locator = $this->createMock(LibreOfficeBinaryLocator::class);
        $locator->method('locate')->willReturn(null);

        $checker = new RuntimeRequirementsChecker($locator);
        $config  = ResolvedConfig::fromArray(['temp_dir' => sys_get_temp_dir()]);

        try {
            $checker->assertReady($config);
            self::fail('Expected MissingDependencyException');
        } catch (MissingDependencyException $e) {
            self::assertStringContainsString('libreoffice-writer', $e->getMessage());
            self::assertStringContainsString('LibreOffice Writer', $e->getMessage());
        }
    }

    public function testTempDirNotWritable(): void
    {
        $locator = $this->createMock(LibreOfficeBinaryLocator::class);
        $locator->method('locate')->willReturn('/usr/bin/soffice');

        $checker = new RuntimeRequirementsChecker($locator);
        $config  = ResolvedConfig::fromArray(['temp_dir' => '/this/path/does/not/exist/wordtopdf']);

        $this->expectException(MissingDependencyException::class);
        $this->expectExceptionMessage('Temporary directory');
        $checker->assertReady($config);
    }

    public function testDiagnoseFailure(): void
    {
        $locator = $this->createMock(LibreOfficeBinaryLocator::class);
        $locator->method('locate')->willReturn(null);
        $checker = new RuntimeRequirementsChecker($locator);
        $result  = $checker->diagnose(ResolvedConfig::fromArray([]));
        self::assertFalse($result['ok']);
        self::assertNull($result['binary']);
        self::assertStringContainsString('libreoffice-writer', $result['message']);
    }

    public function testIsReadyFalse(): void
    {
        $locator = $this->createMock(LibreOfficeBinaryLocator::class);
        $locator->method('locate')->willReturn(null);
        $checker = new RuntimeRequirementsChecker($locator);
        self::assertFalse($checker->isReady(ResolvedConfig::fromArray([])));
    }

    public function testLocatorCandidates(): void
    {
        $locator = new LibreOfficeBinaryLocator();
        self::assertFalse($locator->isUsableBinary('/nonexistent/soffice'));
        self::assertContains('/usr/bin/soffice', LibreOfficeBinaryLocator::CANDIDATE_PATHS);
    }
}
