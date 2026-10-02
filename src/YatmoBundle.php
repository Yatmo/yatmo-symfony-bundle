<?php

declare(strict_types=1);

namespace Yatmo\SymfonyBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The Yatmo bundle: configuration under `yatmo:`, the `Yatmo\Client` service, the cached `Yatmo` service and
 * the Twig functions `yatmo_map`, `yatmo_text` and `yatmo_pois`.
 *
 *   # config/packages/yatmo.yaml
 *   yatmo:
 *     key: '%env(YATMO_KEY)%'                     # backend key, server to server
 *     frontend_key: '%env(YATMO_FRONTEND_KEY)%'   # the key locked to your domains, for the map iframe
 *     country: BE
 *     language: ~                                 # null follows the request locale
 */
final class YatmoBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('key')->isRequired()->cannotBeEmpty()->info('Backend key, never sent to the browser.')->end()
                ->scalarNode('frontend_key')->defaultValue('')->info('Frontend key, locked to your domains, embedded in the map iframe.')->end()
                ->scalarNode('country')->defaultValue('BE')->info('Country of your properties: BE, FR, NL, LU, CH, DE, IT, ES, PT, IE, UK, AT, CA, GR, MA, AU, HR, MT, SI, RS, CY, BA, ME, BG, AL.')->end()
                ->scalarNode('language')->defaultNull()->info('Language of labels and texts (EN, FR, NL...). Null follows the request locale.')->end()
                ->integerNode('cache_days')->defaultValue(30)->info('Cache of the text and the places, in days; 0 disables it.')->end()
                ->scalarNode('cache')->defaultValue('cache.app')->info('Service id of the PSR-6 cache pool.')->end()
                ->integerNode('timeout')->defaultValue(15)->end()
                ->arrayNode('map')->info('Defaults of yatmo_map(): any iframe parameter (mode, zoom, marker, isochrone...).')->normalizeKeys(false)->scalarPrototype()->end()->end()
            ->end();
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('yatmo.key', $config['key'])
            ->set('yatmo.frontend_key', $config['frontend_key'])
            ->set('yatmo.country', (string) $config['country'])   // may be an env placeholder: uppercased at runtime
            ->set('yatmo.language', $config['language'] === null ? null : strtoupper((string) $config['language']))
            ->set('yatmo.cache_days', $config['cache_days'])
            ->set('yatmo.timeout', $config['timeout'])
            ->set('yatmo.map', $config['map']);
        $container->import('../config/services.php');
        $container->services()->alias('yatmo.cache_pool', $config['cache']);
    }
}
