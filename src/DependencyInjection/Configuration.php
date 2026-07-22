<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_keys;
use function implode;
use function in_array;
use function sprintf;

/**
 * Root config key: {@see ALIAS} — named profiles + default_profile.
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_word_to_pdf';

    /** @var list<string> */
    public const SUPPORTED_ENGINES = [
        'libreoffice',
    ];

    /** @var list<string> */
    public const BOOT_FAILURE_MODES = [
        'exception',
        'warning',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('engine')
                    ->defaultValue('libreoffice')
                    ->info('Conversion backend: libreoffice (LibreOffice Writer / soffice).')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('default_profile')->defaultValue('default')->end()
                ->arrayNode('profiles')
                    ->useAttributeAsKey('name')
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('binary_path')
                                ->defaultNull()
                                ->info('Absolute path to soffice/libreoffice. Null = auto-detect.')
                            ->end()
                            ->scalarNode('temp_dir')
                                ->defaultNull()
                                ->info('Writable temp directory. Null = sys_get_temp_dir().')
                            ->end()
                            ->integerNode('timeout')
                                ->defaultValue(120)
                                ->min(1)
                                ->info('LibreOffice process timeout in seconds.')
                            ->end()
                            ->integerNode('max_source_bytes')
                                ->defaultValue(52428800)
                                ->min(1)
                                ->info('Maximum source Word file size in bytes (default 50 MiB).')
                            ->end()
                            ->booleanNode('check_on_boot')
                                ->defaultFalse()
                                ->info('When true, assert LibreOffice Writer is available when the kernel boots.')
                            ->end()
                            ->enumNode('boot_failure')
                                ->values(self::BOOT_FAILURE_MODES)
                                ->defaultValue('exception')
                                ->info('When check_on_boot fails: throw exception or log a warning.')
                            ->end()
                            ->scalarNode('min_version')
                                ->defaultNull()
                                ->info('Optional minimum LibreOffice version (e.g. "24.2").')
                            ->end()
                            ->scalarNode('filter')
                                ->defaultValue('pdf:writer_pdf_Export')
                                ->cannotBeEmpty()
                                ->info('LibreOffice convert-to filter string.')
                            ->end()
                            ->arrayNode('export')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('filename')->defaultValue('document.pdf')->end()
                                    ->scalarNode('storage')->defaultValue('memory')->end()
                                    ->scalarNode('local_path')->defaultNull()->end()
                                    ->scalarNode('flysystem_adapter')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
            ->always(static function (array $v): array {
                if (!in_array($v['engine'], self::SUPPORTED_ENGINES, true)) {
                    throw new InvalidConfigurationException(sprintf('nowo_word_to_pdf.engine must be one of: %s (got "%s").', implode(', ', self::SUPPORTED_ENGINES), $v['engine']));
                }

                if (!isset($v['profiles'][$v['default_profile']])) {
                    throw new InvalidConfigurationException(sprintf('nowo_word_to_pdf.default_profile ("%s") must exist in nowo_word_to_pdf.profiles (keys: %s).', $v['default_profile'], implode(', ', array_keys($v['profiles']))));
                }

                return $v;
            })
            ->end();

        return $treeBuilder;
    }
}
