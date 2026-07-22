<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Config;

use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\InvalidProfileException;
use PHPUnit\Framework\TestCase;

final class ProfileResolverTest extends TestCase
{
    public function testResolveDefaultAndMerge(): void
    {
        $resolver = new ProfileResolver([
            'default' => [
                'timeout' => 60,
                'filter'  => 'pdf:writer_pdf_Export',
                'export'  => ['filename' => 'a.pdf'],
            ],
            'fast' => [
                'timeout' => 30,
            ],
        ], 'default');

        $cfg = $resolver->resolve('fast', ['export' => ['filename' => 'b.pdf']]);
        self::assertSame(30, $cfg->timeout);
        self::assertSame('b.pdf', $cfg->export['filename']);
        self::assertSame('default', $resolver->getDefaultProfileKey());
    }

    public function testUnknownProfileThrows(): void
    {
        $resolver = new ProfileResolver(['default' => []], 'default');
        $this->expectException(InvalidProfileException::class);
        $resolver->resolve('missing');
    }

    public function testResolveInline(): void
    {
        $resolver = new ProfileResolver(['default' => []], 'default');
        $cfg      = $resolver->resolveInline(['timeout' => 10, 'binary_path' => '/usr/bin/soffice']);
        self::assertSame(10, $cfg->timeout);
        self::assertSame('/usr/bin/soffice', $cfg->binaryPath);
    }

    public function testToArrayRoundTrip(): void
    {
        $cfg = ResolvedConfig::fromArray([
            'binary_path'      => null,
            'temp_dir'         => '/tmp',
            'timeout'          => 5,
            'max_source_bytes' => 1000,
            'check_on_boot'    => true,
            'boot_failure'     => 'warning',
            'min_version'      => '24.2',
            'filter'           => 'pdf',
            'export'           => ['filename' => 'x.pdf'],
        ]);
        $arr = $cfg->toArray();
        self::assertSame('/tmp', $arr['temp_dir']);
        self::assertSame('warning', $arr['boot_failure']);
        self::assertSame('x.pdf', $arr['export']['filename']);
    }
}
