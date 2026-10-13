<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\DependencyInjection;

use Nowo\WordToPdfBundle\DependencyInjection\Configuration;
use Nowo\WordToPdfBundle\DependencyInjection\WordToPdfExtension;
use Nowo\WordToPdfBundle\Export\PdfExporter;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

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
        self::assertSame(10, $processed['max_batch_size']);
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
        self::assertSame(10, $container->getParameter('nowo_word_to_pdf.max_batch_size'));
        self::assertSame(Configuration::ALIAS, $extension->getAlias());
    }

    public function testNoFlysystemAdapterKeepsExporterLocatorNull(): void
    {
        $container = new ContainerBuilder();
        (new WordToPdfExtension())->load([['profiles' => ['default' => []]]], $container);

        self::assertNull($container->getDefinition(PdfExporter::class)->getArgument('$flysystemAdapters'));
    }

    public function testProfileFlysystemAdaptersAreExposedThroughLocator(): void
    {
        $container = new ContainerBuilder();
        (new WordToPdfExtension())->load([[
            'profiles' => [
                'default' => ['export' => ['flysystem_adapter' => 'app.default_storage']],
                'archive' => ['export' => ['flysystem_adapter' => ' app.archive_storage ']],
                'blank'   => ['export' => ['flysystem_adapter' => '  ']],
            ],
        ]], $container);

        $locatorRef = $container->getDefinition(PdfExporter::class)->getArgument('$flysystemAdapters');
        self::assertInstanceOf(Reference::class, $locatorRef);
        $locator = $container->getDefinition((string) $locatorRef);
        self::assertSame(ServiceLocator::class, $locator->getClass());
        $factories = $locator->getArgument(0);
        self::assertIsArray($factories);
        self::assertSame(['app.archive_storage', 'app.default_storage'], array_keys($factories));
    }

    public function testDefaultsDoNotTriggerDeprecations(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['profiles' => ['default' => []]]]);

        self::assertSame('memory', $config['profiles']['default']['export']['storage']);
    }

    #[IgnoreDeprecations]
    public function testDeprecatedNoOpExportKeysTriggerDeprecations(): void
    {
        foreach (['storage', 'local_path'] as $key) {
            $this->expectUserDeprecationMessageMatches('/^Since nowo-tech\/word-to-pdf-bundle 1\.4: The "nowo_word_to_pdf\.profiles\.default\.export\.' . $key . '" option is deprecated/');
        }

        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => ['default' => ['export' => ['storage' => 'local', 'local_path' => '/tmp', 'flysystem_adapter' => 'app.fs']]],
        ]]);

        self::assertSame('app.fs', $config['profiles']['default']['export']['flysystem_adapter']);
    }
}
