<?php

namespace App\Services\GoogleFavicons;

use App\Services\ApiConnector;

/** Google's favicon service. */
class GoogleFaviconsConnector extends ApiConnector
{
    private const TIMEOUT_SECONDS = 8;

    public function resolveBaseUrl(): string
    {
        return 'https://www.google.com/s2/favicons';
    }

    /** @return array<string, int> */
    protected function defaultConfig(): array
    {
        return ['timeout' => self::TIMEOUT_SECONDS];
    }
}
