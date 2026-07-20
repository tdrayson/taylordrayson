<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client for the logo.dev image API.
 *
 * Fetches a brand logo as raw image bytes, keyed by the brand's web domain.
 * The `fallback=404` parameter makes logo.dev return a 404 (rather than a
 * generated monogram placeholder) when it has no real logo, so a miss is
 * reported as unavailable instead of saved.
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
