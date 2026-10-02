<?php

declare(strict_types=1);

namespace Yatmo\SymfonyBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Yatmo\Exception\YatmoException;
use Yatmo\SymfonyBundle\Yatmo;

/**
 * `{{ yatmo_map(lat, lng, {marker: 'circle', isochrone: 'right'}) }}`: the map iframe.
 * `{{ yatmo_text(lat, lng, {titles: 'city'}) }}`: the neighbourhood text, rendered on the server and cached.
 * `{{ yatmo_pois(lat, lng, {categories: 'education,shopping'}) }}`: the nearest places with travel times.
 * The text and the places render nothing, and log nothing fatal, when Yatmo cannot be reached: a page never breaks.
 */
final class YatmoExtension extends AbstractExtension
{
    public function __construct(private readonly Yatmo $yatmo)
    {
    }

    public function getFunctions(): array
    {
        $safe = ['is_safe' => ['html']];

        return [
            new TwigFunction('yatmo_map', [$this, 'map'], $safe),
            new TwigFunction('yatmo_text', [$this, 'text'], $safe),
            new TwigFunction('yatmo_pois', [$this, 'pois'], $safe),
            new TwigFunction('yatmo_map_url', [$this->yatmo, 'mapUrl']),
        ];
    }

    /** @param array<string, scalar|null> $options */
    public function map(float $latitude, float $longitude, array $options = []): string
    {
        return $this->yatmo->mapHtml($latitude, $longitude, $options);
    }

    /** @param array{heading?: ?string, titles?: string, paragraphs?: string[]|string, strong?: bool, class?: string, language?: string} $options */
    public function text(float $latitude, float $longitude, array $options = []): string
    {
        $language = $options['language'] ?? null;
        unset($options['language']);
        if (\is_string($options['paragraphs'] ?? null)) {
            $options['paragraphs'] = array_filter(array_map('trim', explode(',', $options['paragraphs'])));
        }
        try {
            return $this->yatmo->textHtml($latitude, $longitude, $options, $language);
        } catch (YatmoException) {
            return '';
        }
    }

    /** @param array{categories?: string|string[], mode?: string, limit?: int, heading?: string} $options */
    public function pois(float $latitude, float $longitude, array $options = []): string
    {
        try {
            return $this->yatmo->poisHtml($latitude, $longitude, $options);
        } catch (YatmoException) {
            return '';
        }
    }
}
