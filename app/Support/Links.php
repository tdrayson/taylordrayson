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
     * Every external URL a Portable Text document links to, deduplicated and in
     * document order.
     *
     * Sibling of hostsIn(): favicons only need the host, but a webmention has
     * to be sent to the exact URL that was linked.
     *
     * @param  ?array<int, mixed>  $blocks
     * @return list<string>
     */
    public static function urlsIn(?array $blocks): array
    {
        $urls = [];

        foreach ($blocks ?? [] as $block) {
            foreach ($block['markDefs'] ?? [] as $def) {
                $href = $def['href'] ?? null;

                if (($def['_type'] ?? null) !== 'link' || ! is_string($href) || ! str_starts_with($href, 'http')) {
                    continue;
                }

                // Our own URLs are skipped: an internal link already renders as
                // a link preview, so mentioning ourselves would duplicate it.
                if (self::internalPath($href) !== null) {
                    continue;
                }

                $urls[$href] = true;
            }
        }

        return array_keys($urls);
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
     * The site-relative path an href points at, or null when it leaves the site.
     *
     * An author typing a link writes a path, but one pasted from the address bar
     * carries the whole address. Both name the same page, so both have to reach
     * the same resolver rather than the absolute form being read as somebody
     * else's site.
     */
    public static function internalPath(string $href): ?string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $href)) {
            // A path is already what we want; a mailto: or tel: is not a page.
            return str_starts_with($href, '/') ? $href : null;
        }

        if (self::host($href) !== self::host((string) config('app.url'))) {
            return null;
        }

        $path = parse_url($href, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * Every external host a document links to, deduplicated. A link back to this
     * site is not one, however it was written.
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

                if (self::internalPath($href) !== null) {
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
