<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\DependencyInjection;

use Nowo\WordToPdfBundle\EventListener\RuntimeBootCheckListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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
        $container->setParameter(Configuration::ALIAS . '.profiles', $config['profiles']);

        $defaultProfile = $config['profiles'][$config['default_profile']];
        $bootCheck      = (bool) ($defaultProfile['check_on_boot'] ?? false);
        $bootFailure    = (string) ($defaultProfile['boot_failure'] ?? 'exception');

        $listener = $container->getDefinition(RuntimeBootCheckListener::class);
        $listener->setArgument('$enabled', $bootCheck);
        $listener->setArgument('$bootFailure', $bootFailure);
        if (!$bootCheck) {
            $listener->clearTag('kernel.event_subscriber');
        }
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
