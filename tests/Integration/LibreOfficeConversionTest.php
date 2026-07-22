<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Integration;

use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Converter\WordToPdfConverter;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeBinaryLocator;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeProcessRunner;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * Requires LibreOffice Writer (libreoffice-writer / soffice) on the host.
 */
#[Group('libreoffice')]
final class LibreOfficeConversionTest extends TestCase
{
    public function testConvertMinimalDocxWhenLibreOfficeAvailable(): void
    {
        $locator = new LibreOfficeBinaryLocator();
        $binary  = $locator->locate();
        if ($binary === null) {
            self::markTestSkipped('LibreOffice Writer (soffice) is not installed. Install libreoffice-writer to run this test.');
        }

        $fixture = dirname(__DIR__) . '/Fixtures/minimal.docx';
        if (!is_file($fixture)) {
            self::markTestSkipped('Fixture minimal.docx missing.');
        }

        $converter = new WordToPdfConverter(
            new ProfileResolver(['default' => ['timeout' => 180]], 'default'),
            new RuntimeRequirementsChecker($locator),
            new LibreOfficeProcessRunner(),
        );

        $pdf = $converter->convert($fixture);
        try {
            $bytes = $pdf->readContents();
            self::assertStringStartsWith('%PDF', $bytes);
        } finally {
            $pdf->dispose();
        }
    }
}
