<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Runtime;

use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeProcessRunner;
use PHPUnit\Framework\TestCase;

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
}
