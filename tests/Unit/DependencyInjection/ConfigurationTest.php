<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\DependencyInjection;

use Nowo\WordToPdfBundle\DependencyInjection\Configuration;
use Nowo\WordToPdfBundle\DependencyInjection\WordToPdfExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ConfigurationTest extends TestCase
{
    public function testDefaultProfileMustExist(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'default_profile' => 'missing',
            'profiles'        => ['default' => []],
        ]]);
    }

    public function testValidConfig(): void
    {
        $processed = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'timeout' => 90,
                ],
            ],
        ]]);

        self::assertSame('libreoffice', $processed['engine']);
        self::assertSame('default', $processed['default_profile']);
        self::assertSame(90, $processed['profiles']['default']['timeout']);
        self::assertSame('pdf:writer_pdf_Export', $processed['profiles']['default']['filter']);
    }

    public function testDefaultTimeoutIsProcessTimeoutConvention(): void
    {
        $processed = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [],
            ],
        ]]);

        self::assertSame(180, $processed['profiles']['default']['timeout']);
    }

    public function testInvalidEngine(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'engine'   => 'dompdf',
            'profiles' => ['default' => []],
        ]]);
    }

    public function testExtensionLoadsParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new WordToPdfExtension();
        $extension->load([[
            'profiles' => [
                'default' => [
                    'check_on_boot' => false,
                ],
            ],
        ]], $container);

        self::assertTrue($container->hasParameter('nowo_word_to_pdf.profiles'));
        self::assertSame('default', $container->getParameter('nowo_word_to_pdf.default_profile'));
        self::assertSame(Configuration::ALIAS, $extension->getAlias());
    }
}
