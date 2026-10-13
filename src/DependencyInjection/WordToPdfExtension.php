<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\DependencyInjection;

use Nowo\WordToPdfBundle\Converter\WordToPdfConverter;
use Nowo\WordToPdfBundle\EventListener\RuntimeBootCheckListener;
use Nowo\WordToPdfBundle\Export\PdfExporter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;
use function ksort;
use function trim;

/**
 * DI extension for alias nowo_word_to_pdf.
 */
final class WordToPdfExtension extends Extension
{
    /**
     * Load and process bundle configuration into the container.
     *
     * @param list<array<string, mixed>> $configs Raw configs
     * @param ContainerBuilder $container Service container
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(Configuration::ALIAS . '.engine', $config['engine']);
        $container->setParameter(Configuration::ALIAS . '.default_profile', $config['default_profile']);
        $container->setParameter(Configuration::ALIAS . '.max_batch_size', $config['max_batch_size']);
        $container->setParameter(Configuration::ALIAS . '.profiles', $config['profiles']);

        $converter = $container->getDefinition(WordToPdfConverter::class);
        $converter->setArgument('$maxBatchSize', $config['max_batch_size']);

        $defaultProfile = $config['profiles'][$config['default_profile']];
        $bootCheck      = (bool) ($defaultProfile['check_on_boot'] ?? false);
        $bootFailure    = (string) ($defaultProfile['boot_failure'] ?? 'exception');

        $listener = $container->getDefinition(RuntimeBootCheckListener::class);
        $listener->setArgument('$enabled', $bootCheck);
        $listener->setArgument('$bootFailure', $bootFailure);
        if (!$bootCheck) {
            $listener->clearTag('kernel.event_subscriber');
        }

        $this->registerFlysystemAdapters($container, $config['profiles']);
    }

    /**
     * Exposes every {@code profiles.*.export.flysystem_adapter} service id to {@see PdfExporter} through a
     * service locator (only ids declared in YAML are reachable; a missing service fails at container compile).
     *
     * @param array<string, array<string, mixed>> $profiles
     */
    private function registerFlysystemAdapters(ContainerBuilder $container, array $profiles): void
    {
        $refs = [];
        foreach ($profiles as $profile) {
            $id = $profile['export']['flysystem_adapter'] ?? null;
            if (is_string($id) && trim($id) !== '') {
                $id        = trim($id);
                $refs[$id] = new Reference($id);
            }
        }

        if ($refs === []) {
            return;
        }

        ksort($refs);
        $container->getDefinition(PdfExporter::class)
            ->setArgument('$flysystemAdapters', ServiceLocatorTagPass::register($container, $refs));
    }

    /**
     * Return the extension alias nowo_word_to_pdf.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
