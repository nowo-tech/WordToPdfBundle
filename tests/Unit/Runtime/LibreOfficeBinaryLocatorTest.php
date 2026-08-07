<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Runtime;

use Nowo\WordToPdfBundle\Runtime\LibreOfficeBinaryLocator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use const PATH_SEPARATOR;

final class LibreOfficeBinaryLocatorTest extends TestCase
{
    public function testLocateConfiguredPath(): void
    {
        $script = sys_get_temp_dir() . '/wtp_bin_' . uniqid('', true);
        file_put_contents($script, "#!/bin/sh\necho ok\n");
        chmod($script, 0755);

        $locator = new LibreOfficeBinaryLocator();
        self::assertSame($script, $locator->locate($script));
        self::assertNull($locator->locate($script . '-missing'));
        @unlink($script);
    }

    public function testLocateOnPath(): void
    {
        $dir = sys_get_temp_dir() . '/wtp_path_' . uniqid('', true);
        mkdir($dir);
        $script = $dir . '/soffice';
        file_put_contents($script, "#!/bin/sh\necho ok\n");
        chmod($script, 0755);

        try {
            $locator = new LibreOfficeBinaryLocator([], $dir);
            self::assertSame($script, $locator->locate());
        } finally {
            @unlink($script);
            @rmdir($dir);
        }
    }

    public function testLocateCandidatePath(): void
    {
        $dir = sys_get_temp_dir() . '/wtp_cand_' . uniqid('', true);
        mkdir($dir);
        $script = $dir . '/soffice';
        file_put_contents($script, "#!/bin/sh\necho ok\n");
        chmod($script, 0755);

        $locator = new LibreOfficeBinaryLocator([$script]);
        self::assertSame($script, $locator->locate());

        @unlink($script);
        @rmdir($dir);
    }

    public function testFindOnPathEmptySegmentAndMiss(): void
    {
        $locator = new LibreOfficeBinaryLocator([], PATH_SEPARATOR . '/tmp/no-such-wtp-bin-' . uniqid());
        self::assertNull($locator->locate());
    }

    public function testEmptyPathEnv(): void
    {
        $locator = new LibreOfficeBinaryLocator([], '');
        self::assertNull($locator->locate());
    }

    public function testLocateSkipsEmptyCandidatePaths(): void
    {
        $dir = sys_get_temp_dir() . '/wtp_cand_empty_' . uniqid('', true);
        mkdir($dir);
        $script = $dir . '/soffice';
        file_put_contents($script, "#!/bin/sh\necho ok\n");
        chmod($script, 0755);

        try {
            $locator = new LibreOfficeBinaryLocator(['', $script]);
            self::assertSame($script, $locator->locate());
        } finally {
            @unlink($script);
            @rmdir($dir);
        }
    }

    public function testUsableNonEmptyPathRejectsEmptyString(): void
    {
        $locator = new LibreOfficeBinaryLocator([]);
        $method  = new ReflectionMethod(LibreOfficeBinaryLocator::class, 'usableNonEmptyPath');
        self::assertNull($method->invoke($locator, ''));
    }
}
