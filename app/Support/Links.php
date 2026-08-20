<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Shared helpers for the links inside a Portable Text document: which hosts a
 * document points at, and where that host's favicon lives once stored.
 */
final class Links
{
    /**
     * Filename-safe form of a host, so one domain maps to one stored file.
     * Dots are kept: they are legal in a filename, and dropping them would let
     * two different domains collide on one file.
     */
    public static function key(string $host): string
    {
        return preg_replace('/[^a-z0-9.-]/', '', strtolower($host)) ?? '';
    }

    /**
     * The display host for a URL: lowercase, without a leading www.
     */
    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Every external host a document links to, deduplicated.
     *
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return list<string>
     */
    public static function hostsIn(?array $blocks): array
    {
        $hosts = [];

        foreach ($blocks ?? [] as $block) {
            foreach ($block['markDefs'] ?? [] as $def) {
                $href = $def['href'] ?? null;

                if (($def['_type'] ?? null) !== 'link' || ! is_string($href) || ! str_starts_with($href, 'http')) {
                    continue;
                }

                $host = self::host($href);

                if ($host !== null) {
                    $hosts[$host] = true;
                }
            }
        }

        return array_keys($hosts);
    }

    /**
     * Where a host's favicon is written. Mirrors the fuel brand logos: a flat
     * public directory keyed by name, no Media Library, since a favicon needs
     * no conversions and one domain has exactly one.
     */
    public static function faviconPath(string $host): string
    {
        return public_path('favicons/'.self::key($host).'.png');
    }

    /**
     * The public URL for a stored favicon, or null when nothing is stored.
     */
    public static function faviconUrl(string $host): ?string
    {
        return File::exists(self::faviconPath($host))
            ? '/favicons/'.self::key($host).'.png'
            : null;
    }
}
