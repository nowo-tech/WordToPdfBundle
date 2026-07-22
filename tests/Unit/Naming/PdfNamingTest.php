<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Naming;

use Nowo\WordToPdfBundle\Naming\PdfNaming;
use PHPUnit\Framework\TestCase;

use function pathinfo;
use function sprintf;

use const PATHINFO_FILENAME;

final class PdfNamingTest extends TestCase
{
    public function testKeepPrefixSuffixSurroundFixed(): void
    {
        $path = '/tmp/docs/contract.docx';

        self::assertSame('contract.pdf', PdfNaming::keep()->resolve($path));
        self::assertSame('OUT-contract.pdf', PdfNaming::prefix('OUT-')->resolve($path));
        self::assertSame('contract [converted].pdf', PdfNaming::suffix(' [converted]')->resolve($path));
        self::assertSame('OUT-contract [converted].pdf', PdfNaming::surround('OUT-', ' [converted]')->resolve($path));
        self::assertSame('report.pdf', PdfNaming::fixed('report')->resolve($path));
        self::assertSame('report.pdf', PdfNaming::fixed('report.pdf')->resolve($path));
    }

    public function testCallbackAndEmptyBasename(): void
    {
        $naming = PdfNaming::callback(static fn (string $source, int $index): string => sprintf('batch-%d-%s', $index, pathinfo($source, PATHINFO_FILENAME)));
        self::assertSame('batch-2-a.pdf', $naming->resolve('/tmp/a.docx', 2));

        self::assertSame('document.pdf', PdfNaming::keep()->resolve('/tmp/.docx'));
        self::assertSame('document.pdf', PdfNaming::ensurePdfExtension(''));
        self::assertSame('x.pdf', PdfNaming::ensurePdfExtension('/evil/../x'));
    }
}
