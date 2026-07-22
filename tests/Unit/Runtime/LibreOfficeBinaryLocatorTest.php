<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Runtime;

use Nowo\WordToPdfBundle\Runtime\LibreOfficeBinaryLocator;
use PHPUnit\Framework\TestCase;

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

        $prev = getenv('PATH');
        putenv('PATH=' . $dir);
        try {
            $locator = new LibreOfficeBinaryLocator();
            self::assertSame($script, $locator->locate());
        } finally {
            putenv('PATH=' . ($prev === false ? '' : $prev));
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
        $prev = getenv('PATH');
        putenv('PATH=' . PATH_SEPARATOR . '/tmp/no-such-wtp-bin-' . uniqid());
        try {
            $locator = new LibreOfficeBinaryLocator([]);
            self::assertNull($locator->locate());
        } finally {
            putenv('PATH=' . ($prev === false ? '' : $prev));
        }
    }

    public function testEmptyPathEnv(): void
    {
        $prev = getenv('PATH');
        putenv('PATH=');
        try {
            $locator = new LibreOfficeBinaryLocator([]);
            self::assertNull($locator->locate());
        } finally {
            putenv('PATH=' . ($prev === false ? '' : $prev));
        }
    }
}
