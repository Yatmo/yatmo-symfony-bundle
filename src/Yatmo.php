<?php

declare(strict_types=1);

namespace Yatmo\SymfonyBundle;

use Psr\Cache\CacheItemPoolInterface;
use Yatmo\Client;
use Yatmo\Model\Scores;
use Yatmo\Model\Summary;
use Yatmo\Model\SummaryCategory;
use Yatmo\Model\SummaryText;
use Yatmo\TextRenderer;
use Yatmo\TravelMode;

/**
 * The Yatmo service of the application: the client plus what a property page needs, cached for
 * `yatmo.cache_days` days as the plain arrays the API sent (any PSR-6 pool stores them).
 */
final class Yatmo
{
    public const IFRAME_URL = 'https://map.yatmo.com/plugin.html';

    /** @param array<string, scalar> $mapDefaults */
    public function __construct(
        private readonly Client $client,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $country,
        private readonly string $frontendKey,
        private readonly int $cacheDays,
        private readonly array $mapDefaults = [],
    ) {
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function country(): string
    {
        return strtoupper($this->country);
    }

    public function frontendKey(): string
    {
        return $this->frontendKey;
    }

    /** The neighbourhood text in the given language (default: the client's), cached. */
    public function text(float $latitude, float $longitude, ?string $language = null): SummaryText
    {
        $raw = $this->remember('text', $latitude, $longitude, fn () => $this->client->summaryTextRaw($latitude, $longitude));

        return SummaryText::fromWire($raw, strtoupper($language ?? $this->client->language()));
    }

    /**
     * The neighbourhood text as HTML, ready for `|raw` in Twig.
     *
     * @param array{heading?: ?string, titles?: string, paragraphs?: string[], strong?: bool, class?: string} $options
     */
    public function textHtml(float $latitude, float $longitude, array $options = [], ?string $language = null): string
    {
        return TextRenderer::html($this->text($latitude, $longitude, $language), $options);
    }

    /** The nearest places by category with travel times, cached. */
    public function summary(float $latitude, float $longitude): Summary
    {
        return Summary::fromWire($this->remember('summary', $latitude, $longitude, fn () => $this->client->summaryRaw($latitude, $longitude)));
    }

    /** One 0 to 10 score per category, cached. */
    public function scores(float $latitude, float $longitude): Scores
    {
        return Scores::fromWire($this->remember('scores', $latitude, $longitude, fn () => $this->client->scoresRaw($latitude, $longitude)));
    }

    /**
     * The nearest places as HTML headings and lists: `<div class="yatmo-pois"><h3>Nursery</h3><ul><li>...`.
     *
     * @param array{categories?: string|string[], mode?: string, limit?: int, heading?: string} $options categories among education, transport, shopping, tourism
     */
    public function poisHtml(float $latitude, float $longitude, array $options = []): string
    {
        $types = ['education' => SummaryCategory::EDUCATION, 'transport' => SummaryCategory::TRANSPORT, 'shopping' => SummaryCategory::SHOPPING, 'tourism' => SummaryCategory::TOURISM];
        $names = \is_string($options['categories'] ?? null) ? explode(',', $options['categories']) : ($options['categories'] ?? []);
        $wanted = array_values(array_filter(array_map(fn ($n) => $types[strtolower(trim((string) $n))] ?? null, $names)));
        $mode = TravelMode::tryFrom(ucfirst(strtolower((string) ($options['mode'] ?? 'walking')))) ?? TravelMode::Walking;
        $limit = max(1, (int) ($options['limit'] ?? 1));
        $heading = \in_array($options['heading'] ?? 'h3', ['h2', 'h3', 'h4', 'h5', 'h6'], true) ? $options['heading'] ?? 'h3' : 'h3';
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $html = '';
        foreach ($this->summary($latitude, $longitude)->subCategories(...$wanted) as $sub) {
            $places = \array_slice($sub->places, 0, $limit);
            if ($places === []) {
                continue;
            }
            $label = \count($places) === 1 && $sub->singularLabel ? $sub->singularLabel : $sub->label;
            $items = '';
            foreach ($places as $place) {
                $travel = $place->travel($mode);
                $detail = $travel ? ' (' . implode(', ', array_filter([$travel->distanceShortLabel, $travel->travelTimeShortLabel])) . ')' : '';
                $items .= '<li>' . $e($place->name) . $e($detail) . '</li>';
            }
            $html .= sprintf('<div class="yatmo-pois-group"><%1$s>%2$s</%1$s><ul>%3$s</ul></div>', $heading, $e($label), $items);
        }

        return '<div class="yatmo-pois">' . $html . '</div>';
    }

    /**
     * The map iframe with the frontend key: `<iframe class="yatmo-map" src="https://map.yatmo.com/plugin.html?...">`.
     *
     * @param array<string, scalar|null> $options Any iframe parameter (mode, zoom, mapStyle, accentColor, marker, circleRadiusInMeters, rounded, isochrone, routeFrom, userId...), plus height, title, class, language, country, key
     */
    public function mapHtml(float $latitude, float $longitude, array $options = []): string
    {
        $options = array_merge($this->mapDefaults, $options);
        $height = (string) ($options['height'] ?? '560px');
        $height = preg_match('/^\d+(\.\d+)?$/', $height) ? $height . 'px' : $height;
        $title = (string) ($options['title'] ?? 'Map and neighbourhood of the property');
        $class = trim('yatmo-map ' . (string) ($options['class'] ?? ''));
        unset($options['height'], $options['title'], $options['class']);
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<iframe class="%s" src="%s" title="%s" loading="lazy" allow="fullscreen" style="display:block;width:100%%;height:%s;border:0"></iframe>',
            $e($class), $e($this->mapUrl($latitude, $longitude, $options)), $e($title), $e($height)
        );
    }

    /** @param array<string, scalar|null> $options */
    public function mapUrl(float $latitude, float $longitude, array $options = []): string
    {
        $params = [
            'licenseKey' => (string) ($options['key'] ?? $this->frontendKey),
            'country' => strtoupper((string) ($options['country'] ?? $this->country())),
            'language' => strtoupper((string) ($options['language'] ?? $this->client->language())),
            'latitude' => number_format($latitude, 7, '.', ''),
            'longitude' => number_format($longitude, 7, '.', ''),
        ];
        unset($options['key'], $options['country'], $options['language']);
        foreach ($options as $name => $value) {
            if ($value !== null && $value !== '' && $value !== false) {
                $params[$name] = $name === 'rounded' && is_numeric($value) ? $value . 'px' : (string) $value;
            }
        }

        return self::IFRAME_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Forgets the cached data of a position. */
    public function forget(float $latitude, float $longitude): void
    {
        foreach (['text', 'summary', 'scores'] as $kind) {
            $this->cache->deleteItem($this->key($kind, $latitude, $longitude));
        }
    }

    /**
     * @param callable(): array<string, mixed> $load
     * @return array<string, mixed>
     */
    private function remember(string $kind, float $latitude, float $longitude, callable $load): array
    {
        if ($this->cacheDays <= 0) {
            return $load();
        }
        $item = $this->cache->getItem($this->key($kind, $latitude, $longitude));
        if ($item->isHit() && \is_array($item->get())) {
            return $item->get();
        }
        $value = $load();
        $item->set($value)->expiresAfter($this->cacheDays * 86400);
        $this->cache->save($item);

        return $value;
    }

    private function key(string $kind, float $latitude, float $longitude): string
    {
        $language = $kind === 'text' ? '' : '.' . $this->client->language();

        return sprintf('yatmo.%s.%s.%s.%s%s', $kind, $this->country(), number_format($latitude, 6, '.', ''), number_format($longitude, 6, '.', ''), $language);
    }
}
