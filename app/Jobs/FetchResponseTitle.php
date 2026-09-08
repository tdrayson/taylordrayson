<?php

namespace App\Jobs;

use App\Support\Links;
use App\Support\SafeFetch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use MensBeam\Microformats;

/**
 * Reads what the page a post responds to calls itself, so the byline can say
 * "Replied to How to send webmentions" instead of "a post on aaronparecki.com".
 *
 * Best effort by design. A site that is down, slow, or gives us nothing usable
 * costs the post nothing: it keeps the name of its host, which is true either
 * way and needs no network to know.
 */
class FetchResponseTitle implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 2;

    private const TIMEOUT_SECONDS = 10;

    private const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Past this an mf2 name is not a title but the whole text of an element
     * that was never marked up. The same cap ParseMentionSource applies to a
     * name it reads off somebody else's page, for the same reason.
     */
    private const MAX_TITLE = 120;

    public function __construct(private readonly Model $post) {}

    public function handle(): void
    {
        $url = (string) $this->post->response_url;

        // A target of mine is drawn from its own card, and the resolver already
        // knows its title without asking the network for it.
        if ($url === '' || Links::internalPath($url) !== null) {
            return;
        }

        $html = SafeFetch::body($url, self::MAX_BYTES, self::TIMEOUT_SECONDS, ['Accept' => 'text/html']);

        if ($html === null) {
            return;
        }

        $title = $this->titleIn($html, $url);

        // Written straight to the column: going through save() would re-run the
        // observers, and one of them queued this job.
        if ($title !== null) {
            $this->post->newQuery()->whereKey($this->post->getKey())->update(['response_title' => $title]);
        }
    }

    /**
     * What the page calls itself, in the order the answers are worth trusting.
     *
     * The post's own microformat name first, because it names the post rather
     * than the page it sits on; og:title next, which a site chose for sharing;
     * the document title last, which usually carries the site name as well.
     */
    private function titleIn(string $html, string $url): ?string
    {
        $mf2 = $this->microformatsName($html, $url);

        if ($mf2 !== null) {
            return $mf2;
        }

        if (preg_match('#<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $matches) === 1) {
            return $this->clean($matches[1]);
        }

        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $matches) === 1) {
            return $this->clean($matches[1]);
        }

        return null;
    }

    /**
     * An h-event as well as an h-entry: what you RSVP to is an event, and a
     * site that marks one up says its name there rather than in a post.
     */
    private function microformatsName(string $html, string $url): ?string
    {
        $parsed = rescue(fn (): array => Microformats::fromString($html, 'text/html', $url), [], report: false);

        foreach ($parsed['items'] ?? [] as $item) {
            if (array_intersect(['h-entry', 'h-event'], $item['type'] ?? []) === []) {
                continue;
            }

            $name = $item['properties']['name'][0] ?? null;

            if (is_string($name) && $this->clean($name) !== null) {
                return $this->clean($name);
            }
        }

        return null;
    }

    /** Collapsed, decoded, and only kept if it is short enough to be a name. */
    private function clean(string $value): ?string
    {
        $title = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)) ?? '');

        return $title === '' || Str::length($title) > self::MAX_TITLE ? null : $title;
    }
}
