<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Yatmo\Client;
use Yatmo\SymfonyBundle\ClientFactory;
use Yatmo\SymfonyBundle\Twig\YatmoExtension;
use Yatmo\SymfonyBundle\Yatmo;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ClientFactory::class)
        ->args([param('yatmo.key'), param('yatmo.country'), param('yatmo.language'), param('yatmo.timeout'), service('request_stack')->nullOnInvalid(), service('Yatmo\Transport\TransportInterface')->nullOnInvalid()]);

    // The typed client of yatmo/yatmo-php, for every endpoint. Inject Yatmo\Client.
    $services->set(Client::class)
        ->factory([service(ClientFactory::class), 'create'])
        ->public();

    // The cached neighbourhood text and places, plus the iframe URL. Inject Yatmo\SymfonyBundle\Yatmo.
    $services->set(Yatmo::class)
        ->args([service(Client::class), service('yatmo.cache_pool'), param('yatmo.country'), param('yatmo.frontend_key'), param('yatmo.cache_days'), param('yatmo.map')])
        ->public();
    $services->alias('yatmo', Yatmo::class)->public();

    $services->set(YatmoExtension::class)
        ->args([service(Yatmo::class)])
        ->tag('twig.extension');
};
