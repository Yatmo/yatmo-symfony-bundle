# Changelog

## 1.0.1

`country` may be an environment placeholder (`%env(YATMO_COUNTRY)%`, as in the Flex recipe): the value is no longer
uppercased at compile time, which made Symfony report the variable as never used and fail `cache:clear`.

## 1.0.0

First release: `yatmo:` configuration (keys from `.env`, country, language or request locale, cache), the
`Yatmo\Client` and `Yatmo` services, Twig functions `yatmo_map`, `yatmo_text`, `yatmo_pois` and `yatmo_map_url`.
Symfony 6.4, 7 and 8, PHP 8.1 to 8.5.
