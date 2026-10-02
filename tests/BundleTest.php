<?php

declare(strict_types=1);

namespace Yatmo\SymfonyBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Environment;
use Yatmo\Client;
use Yatmo\SymfonyBundle\Yatmo;
use Yatmo\SymfonyBundle\YatmoBundle;
use Yatmo\Transport\TransportInterface;

/** Hermetic: a fake transport is registered as a service, the API is never called. */
final class BundleTest extends TestCase
{
    private const TEXT = ['Paragraphs' => [
        ['IconId' => 'shopping', 'Title' => ['FR' => 'Commerces', 'EN' => 'Shops'], 'TitleBis' => ['FR' => 'Commerces près de la Rue de la Loi'], 'TitleTer' => ['FR' => 'Commerces à Bruxelles'],
            'Sentences' => [['FR' => 'Un [STRONG]Carrefour[/STRONG] à 3 minutes.', 'EN' => 'A [STRONG]Carrefour[/STRONG] 3 minutes away.']], 'List' => []],
        ['IconId' => 'education', 'Title' => ['FR' => 'Écoles', 'EN' => 'Schools'], 'Sentences' => [['FR' => 'Deux écoles.', 'EN' => 'Two schools.']], 'List' => []],
    ]];
    private const SUMMARY = ['AvailableCategoriesAroundPosition' => [
        ['ct' => 1, 'l' => 'Education', 'sc' => [['st' => 4, 'l' => 'Nurseries', 'lb' => 'Nursery', 'd' => [['n' => 'Little Bears', 'la' => 50.84, 'lo' => 4.36, 'td' => [['hti' => true, 'tm' => 2, 'ptdsl' => '300 m', 'ttsl' => "4'"]]]]]]],
        ['ct' => 3, 'l' => 'Shopping', 'sc' => [['st' => 9, 'l' => 'Supermarkets', 'lb' => 'Supermarket', 'd' => [['n' => 'Carrefour', 'la' => 50.84, 'lo' => 4.36, 'td' => []], ['n' => 'Delhaize', 'la' => 50.84, 'lo' => 4.36, 'td' => []]]]]],
    ], 'CloseCities' => [], 'PlaceInformation' => null];

    private static ?TestKernel $kernel = null;

    /** @return array<string, mixed> */
    public static function TEXT_PUBLIC(): array { return self::TEXT; }

    /** @return array<string, mixed> */
    public static function SUMMARY_PUBLIC(): array { return self::SUMMARY; }

    private static function twig(): Environment
    {
        $twig = self::kernel()->getContainer()->get('test.service_container')->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }

    private static function kernel(): TestKernel
    {
        if (self::$kernel === null) {
            self::$kernel = new TestKernel();
            self::$kernel->boot();
        }
        FakeTransport::$calls = [];

        return self::$kernel;
    }

    public function testClientAndServiceFromConfiguration(): void
    {
        $container = self::kernel()->getContainer();
        $client = $container->get(Client::class);
        self::assertInstanceOf(Client::class, $client);
        self::assertSame('https://be.yatmo.com/summary?language=FR', $client->url('summary'));
        $yatmo = $container->get('yatmo');
        self::assertInstanceOf(Yatmo::class, $yatmo);
        self::assertSame('frontend-key', $yatmo->frontendKey());
        self::assertSame('BE', $yatmo->country());
    }

    public function testTwigFunctions(): void
    {
        $twig = self::twig();
        $html = $twig->createTemplate("{{ yatmo_map(50.8461, 4.3664, {marker: 'circle', circleRadiusInMeters: 300, isochrone: 'right', rounded: 8, height: 400, class: 'mine'}) }}")->render();
        self::assertStringContainsString('src="https://map.yatmo.com/plugin.html?licenseKey=frontend-key&amp;country=BE&amp;language=FR&amp;latitude=50.8461000&amp;longitude=4.3664000&amp;mode=map-top&amp;zoom=15&amp;marker=circle&amp;circleRadiusInMeters=300&amp;isochrone=right&amp;rounded=8px"', $html);
        self::assertStringContainsString('class="yatmo-map mine"', $html);
        self::assertStringContainsString('height:400px', $html);

        $text = $twig->createTemplate("{{ yatmo_text(50.8461, 4.3664, {heading: 'h2', titles: 'city', paragraphs: 'education'}) }}")->render();
        self::assertSame('<div class="yatmo-text"><h2>Écoles</h2><p>Deux écoles.</p></div>', $text);
        self::assertSame('backend-key', FakeTransport::$calls[0]['headers']['LicenseKey']);

        $english = $twig->createTemplate("{{ yatmo_text(50.8461, 4.3664, {language: 'EN', paragraphs: ['shopping']}) }}")->render();
        self::assertStringContainsString('<h3>Shops</h3><p>A <strong>Carrefour</strong> 3 minutes away.</p>', $english);
        self::assertCount(1, FakeTransport::$calls, 'the second language comes from the cache');

        $pois = $twig->createTemplate("{{ yatmo_pois(50.8461, 4.3664, {categories: 'shopping', limit: 2, heading: 'h4'}) }}")->render();
        self::assertStringContainsString('<h4>Supermarkets</h4><ul><li>Carrefour</li><li>Delhaize</li></ul>', $pois);
        $walk = $twig->createTemplate("{{ yatmo_pois(50.8461, 4.3664, {categories: 'education'}) }}")->render();
        self::assertStringContainsString("<li>Little Bears (300 m, 4&#039;)</li>", $walk);
        self::assertCount(2, FakeTransport::$calls, 'both place lists come from one cached summary');
    }

    public function testFailuresRenderNothing(): void
    {
        $twig = self::twig();
        self::assertSame('', $twig->createTemplate('{{ yatmo_text(0.1, 0.1) }}')->render(), 'the fake transport answers 404 for unknown positions');
        self::assertSame('', $twig->createTemplate('{{ yatmo_pois(0.1, 0.1) }}')->render());
    }
}

final class FakeTransport implements TransportInterface
{
    /** @var array<int, array{url: string, headers: array<string, string>}> */
    public static array $calls = [];

    public function get(string $url, array $headers, int $timeoutSeconds): array
    {
        self::$calls[] = ['url' => $url, 'headers' => $headers];
        if (!str_contains($url, 'latitude=50.8461000')) {
            return ['status' => 404, 'body' => '{"Error":"no canned answer"}'];
        }
        $body = str_contains($url, '/Summary/text?') ? BundleTest::TEXT_PUBLIC() : (str_contains($url, '/summary?') ? BundleTest::SUMMARY_PUBLIC() : null);

        return $body === null ? ['status' => 404, 'body' => '{}'] : ['status' => 200, 'body' => json_encode($body, JSON_THROW_ON_ERROR)];
    }
}

final class TestKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new YatmoBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'handle_all_throwables' => true, 'php_errors' => ['log' => true], 'cache' => ['app' => 'cache.adapter.array']]);
            $container->loadFromExtension('twig', ['strict_variables' => true]);
            $container->loadFromExtension('yatmo', ['key' => 'backend-key', 'frontend_key' => 'frontend-key', 'country' => 'BE', 'language' => 'FR', 'map' => ['mode' => 'map-top', 'zoom' => 15]]);
            $container->register(TransportInterface::class, FakeTransport::class)->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/yatmo-bundle-test/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/yatmo-bundle-test/log';
    }
}
