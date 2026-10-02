# yatmo/symfony-bundle

Add real estate maps, points of interest and neighbourhood data to Symfony applications.
`yatmo/symfony-bundle` is the official Symfony bundle for [Yatmo](https://yatmo.com): three Twig functions put on
a property page the interactive map of the surroundings (schools, shops, public transport, real travel times on
foot, by bike, by car and by transit, isochrones), the nearest places by category, and a written neighbourhood
text rendered on the server and cached, so search engines index it with the listing. Keys in `.env`, the typed
client of `yatmo/yatmo-php` as a service for the rest of the API, 25 countries and 23 languages, Symfony 6.4, 7
and 8.

```bash
composer require yatmo/symfony-bundle
```

```twig
{{ yatmo_map(listing.latitude, listing.longitude, {marker: 'circle', isochrone: 'right'}) }}
{{ yatmo_pois(listing.latitude, listing.longitude, {categories: 'education,transport,shopping'}) }}
{{ yatmo_text(listing.latitude, listing.longitude, {titles: 'city'}) }}
```

<p align="center">
  <img src="https://raw.githubusercontent.com/Yatmo/yatmo-laravel/main/docs/screenshot.png" width="720" alt="A property page with the Yatmo map, the nearest places and the neighbourhood text">
</p>

## Install in 5 minutes

1. Get a Yatmo licence at [yatmo.com](https://yatmo.com): a **backend** key (server to server, kept secret) and
   a **frontend** key (locked to your domains, embedded in the map) ([keys explained](https://documentation.yatmo.com/license)).
2. `composer require yatmo/symfony-bundle`. With Symfony Flex the bundle is registered and
   `config/packages/yatmo.yaml` created; otherwise add `Yatmo\SymfonyBundle\YatmoBundle::class => ['all' => true]`
   to `config/bundles.php` and create the file:

   ```yaml
   # config/packages/yatmo.yaml
   yatmo:
       key: '%env(YATMO_KEY)%'
       frontend_key: '%env(YATMO_FRONTEND_KEY)%'
       country: '%env(YATMO_COUNTRY)%'
       language: ~          # null follows the request locale
   ```

3. `.env`: `YATMO_KEY`, `YATMO_FRONTEND_KEY`, `YATMO_COUNTRY=BE`.
4. Drop the three functions into the listing template. The text and the places are fetched once per property
   and cached for 30 days in `cache.app`.

## Twig functions

| Function | Renders |
|---|---|
| `yatmo_map(lat, lng, options)` | The [iframe plugin](https://documentation.yatmo.com/plugins/iframe) with the frontend key. Options: any iframe parameter (`mode`, `zoom`, `mapStyle`, `accentColor`, `marker`, `circleRadiusInMeters`, `rounded`, `isochrone`, `routeFrom`, `userId`...), plus `height` (default `560px`), `title`, `class`, `language`, `country`. Defaults under `yatmo.map` in the configuration |
| `yatmo_text(lat, lng, options)` | The neighbourhood text as headings and paragraphs, key places in `<strong>`. Options: `heading` (default `h3`), `titles` (`street-city`, `city` for discreet listings, `generic`), `paragraphs` (among `education`, `shopping`, `publictransports`, `transports`, `tourism`, `cities`), `strong`, `class`, `language` |
| `yatmo_pois(lat, lng, options)` | The nearest places by sub-category with distance and travel time, as headings and lists. Options: `categories` (`education`, `transport`, `shopping`, `tourism`), `mode` (`walking`, `bicycling`, `driving`, `transit`), `limit`, `heading` |
| `yatmo_map_url(lat, lng, options)` | The iframe URL alone |

`yatmo_text` and `yatmo_pois` render nothing when Yatmo cannot be reached: a listing page never breaks because of
them. All three are marked safe for HTML.

## Services

- `Yatmo\Client` (from [`yatmo/yatmo-php`](https://github.com/Yatmo/yatmo-php)): every endpoint, typed. The language
  follows the request locale unless `yatmo.language` is set.
- `Yatmo\SymfonyBundle\Yatmo` (alias `yatmo`): `text()`, `textHtml()`, `summary()`, `scores()`, `poisHtml()`,
  `mapHtml()`, `mapUrl()`, `forget()`, with the cache. For a meta description:

  ```php
  $description = u($yatmo->text($lat, $lng)->paragraphs[0]->text())->truncate(155);
  ```

Configuration keys: `key`, `frontend_key`, `country`, `language`, `cache_days` (30, 0 disables), `cache`
(`cache.app`), `timeout`, `map` (defaults of `yatmo_map`). Another HTTP layer (a proxy, HttpClient): register a
service implementing `Yatmo\Transport\TransportInterface` and the client uses it.

## Why

A listing page that describes the neighbourhood, with real travel times to real places, is more useful to the
visitor and brings long-tail search traffic a map alone never brings. Yatmo computes the text and the places from
its own data (official registers, transport feeds, brand networks) and routing engines; Symfony serves them as
plain HTML.

## Tests

```bash
composer test   # hermetic: a fake transport, the API is never called
```

## Links

- [Yatmo](https://yatmo.com) and its [documentation](https://documentation.yatmo.com/api)
- [`yatmo/yatmo-php`](https://github.com/Yatmo/yatmo-php), [`yatmo/laravel`](https://github.com/Yatmo/yatmo-laravel), [WordPress plugin](https://wordpress.org/plugins/yatmo-map/), [Drupal module](https://www.drupal.org/project/yatmo_map), [npm packages](https://www.npmjs.com/org/yatmo)
- [More examples](https://github.com/Yatmo/yatmo-examples)

MIT licence. Yatmo is a paid service for real estate portals, agency networks and developers; a licence key is required.
