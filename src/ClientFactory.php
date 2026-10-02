<?php

declare(strict_types=1);

namespace Yatmo\SymfonyBundle;

use Symfony\Component\HttpFoundation\RequestStack;
use Yatmo\Client;
use Yatmo\Transport\TransportInterface;

/** Builds the `Yatmo\Client` of the configuration; the language follows the request locale when not configured. */
final class ClientFactory
{
    public function __construct(
        private readonly string $key,
        private readonly string $country,
        private readonly ?string $language,
        private readonly int $timeout,
        private readonly ?RequestStack $requests = null,
        private readonly ?TransportInterface $transport = null,
    ) {
    }

    public function create(): Client
    {
        $language = $this->language;
        if ($language === null || $language === '') {
            $locale = $this->requests?->getCurrentRequest()?->getLocale() ?? 'en';
            $language = strtoupper(substr($locale, 0, 3));
            $language = preg_replace('/[^A-Z]/', '', $language) ?: 'EN';
        }

        return new Client($this->key, strtoupper($this->country), $language, $this->transport, $this->timeout);
    }
}
