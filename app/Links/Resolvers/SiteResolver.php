<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Links\LinkResolver;
use App\Support\OgMeta;
use Illuminate\Support\Carbon;

/**
 * The site's own fixed pages: /more, /photos, /on-this-day and the rest.
 *
 * The title and description come from the same {@see OgMeta} payload the page
 * puts in its own head, so a preview can never drift from what the page says
 * about itself, and giving a page metadata is the only step needed to make
 * every link to it resolve.
 *
 * Registered last but one, above {@see PageResolver} only: a Page whose slug
 * collides with a real route must not shadow the route, and these are routes.
 */
class SiteResolver implements LinkResolver
{
    /**
     * Path => the OgMeta method describing it, and the accent its glyph takes.
     * Pages behind auth (/drafts, /new) and the shuffle routes (/lucky) are
     * absent: nothing links to a redirect, and a preview of one would lie.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PAGES = [
        '/' => ['timeline', 'page'],
        '/more' => ['more', 'page'],
        '/photos' => ['gallery', 'page'],
        '/search' => ['search', 'page'],
        '/feeds' => ['feeds', 'page'],
        '/stories' => ['stories', 'page'],
        '/tags' => ['tags', 'page'],
        '/trips' => ['trips', 'page'],
        '/leaderboard' => ['leaderboard', 'page'],
        '/design-system' => ['designSystem', 'page'],
        '/media/tv' => ['series', 'media'],
        '/flights/map' => ['flightMap', 'flight'],
    ];

    public function resolve(string $path): ?LinkPreviewData
    {
        $path = $path === '' ? '/' : rtrim($path, '/');
        $path = $path === '' ? '/' : $path;

        // Dated, so it cannot be a constant like the rest.
        if ($path === '/on-this-day') {
            return $this->from($path, OgMeta::onThisDay(Carbon::today()), 'page');
        }

        if (! isset(self::PAGES[$path])) {
            return null;
        }

        [$method, $accent] = self::PAGES[$path];

        return $this->from($path, OgMeta::{$method}(), $accent);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function from(string $path, array $meta, string $accent): LinkPreviewData
    {
        return LinkPreviewData::site(
            url: $path,
            title: $meta['heading'] ?? $meta['title'] ?? $path,
            excerpt: $meta['description'] ?? null,
            accent: $accent,
        );
    }
}
