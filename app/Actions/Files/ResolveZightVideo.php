<?php

namespace App\Actions\Files;

use App\Data\ZightVideo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A Zight (formerly CloudApp) share page resolved to a playable file and its
 * poster frame, for the one-time import of the old site's embedded videos.
 *
 * A share URL is a web page rather than a video, so no player can be handed
 * one. The page's og:video names Zight's own permanent redirect to the mp4,
 * which is what a <video> tag is given instead.
 */
final class ResolveZightVideo
{
    /** The share hosts, including the CloudApp names Zight kept serving. */
    private const HOSTS = ['share.getcloudapp.com', 'share.zight.com', 'cl.ly'];

    private const TIMEOUT = 15;

    /** Long enough that the import can be re-run for weeks without refetching. */
    private const KEEP_FOR = 2592000;

    /**
     * Null for anything that will not resolve: a foreign URL, a dead share, a
     * page with no video on it. The import behind this walks 127 posts, so a
     * lost video drops its own node rather than the whole run.
     */
    public function __invoke(string $shareUrl): ?ZightVideo
    {
        if (! $this->isShareUrl($shareUrl)) {
            return null;
        }

        $key = 'zight:video:'.sha1($shareUrl);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return new ZightVideo($cached['url'], $cached['poster']);
        }

        $video = $this->fetch($shareUrl);

        // Only a resolution is stored, so a share that failed once still gets
        // another go on the next run.
        if ($video !== null) {
            Cache::put($key, $video->toArray(), self::KEEP_FOR);
        }

        return $video;
    }

    private function isShareUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        return in_array(preg_replace('/^www\./i', '', strtolower($host)), self::HOSTS, true);
    }

    private function fetch(string $shareUrl): ?ZightVideo
    {
        try {
            $response = Http::api()->timeout(self::TIMEOUT)->get($shareUrl);
        } catch (Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $html = $response->body();
        $url = $this->meta($html, 'og:video');

        return $url === null ? null : new ZightVideo($url, $this->meta($html, 'og:image'));
    }

    /** The content of the first meta tag carrying the given property. */
    private function meta(string $html, string $property): ?string
    {
        preg_match_all('/<meta\b[^>]*>/i', $html, $tags);

        foreach ($tags[0] as $tag) {
            if (preg_match('/(?:property|name)=["\']'.preg_quote($property, '/').'["\']/i', $tag) !== 1) {
                continue;
            }

            if (preg_match('/content=["\']([^"\']+)["\']/i', $tag, $content) === 1) {
                return html_entity_decode($content[1], ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }
}
