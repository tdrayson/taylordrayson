<?php

namespace App\Services;

use App\Services\LogoDev\LogoDevConnector;
use App\Services\LogoDev\LogoRequest;

/**
 * Client for the logo.dev image API, fetching a brand logo by web domain.
 */
class LogoDev
{
    public function __construct(private readonly LogoDevConnector $connector) {}

    /**
     * Fetch a brand logo PNG for a web domain as raw image bytes.
     *
     * @return array{status: 'saved'|'unavailable'|'error', body: string|null}
     */
    public function logo(string $domain, int $size = 256): array
    {
        $response = $this->connector->send(new LogoRequest($domain, $size));

        if ($response->status() === 404) {
            return ['status' => 'unavailable', 'body' => null];
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('content-type'), 'image/')) {
            return ['status' => 'error', 'body' => null];
        }

        return ['status' => 'saved', 'body' => $response->body()];
    }
}
