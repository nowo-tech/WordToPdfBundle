<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Exception;

use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use PHPUnit\Framework\TestCase;

final class MissingDependencyExceptionTest extends TestCase
{
    public function testFactories(): void
    {
        $a = MissingDependencyException::libreOfficeWriterNotFound('/x');
        self::assertStringContainsString('/x', $a->getMessage());

        $b = MissingDependencyException::tempDirNotWritable('/tmp/x');
        self::assertStringContainsString('/tmp/x', $b->getMessage());

        $c = MissingDependencyException::versionTooOld('1.0', '2.0');
        self::assertStringContainsString('1.0', $c->getMessage());

        $d = MissingDependencyException::versionUnreadable('/bin/x');
        self::assertStringContainsString('/bin/x', $d->getMessage());
    }
}
