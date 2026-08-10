<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client for the logo.dev image API, fetching a brand logo by web domain.
 * `fallback=404` is set so a miss 404s instead of returning a generated
 * monogram placeholder, which would otherwise be saved as if it were real.
 */
class LogoDev
{
    private const BASE = 'https://img.logo.dev';

    /**
     * Fetch a brand logo PNG for a web domain as raw image bytes.
     *
     * @return array{status: 'saved'|'unavailable'|'error', body: string|null}
     */
    public function logo(string $domain, int $size = 256): array
    {
        $response = Http::get(self::BASE.'/'.$domain, [
            'token' => config('services.logodev.token'),
            'size' => $size,
            'retina' => 'true',
            'format' => 'png',
            'fallback' => '404',
        ]);

        if ($response->status() === 404) {
            return ['status' => 'unavailable', 'body' => null];
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('content-type'), 'image/')) {
            return ['status' => 'error', 'body' => null];
        }

        return ['status' => 'saved', 'body' => $response->body()];
    }
}
