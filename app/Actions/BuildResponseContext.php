<?php

namespace App\Actions;

use App\Data\ResponseData;
use App\Enums\ResponseKind;
use App\Enums\TimelineType;
use App\Links\LinkResolvers;
use App\Support\Links;
use App\Support\PostType;
use Illuminate\Database\Eloquent\Model;

/**
 * The reply context for a post that responds to something, or null for one
 * that does not.
 */
class BuildResponseContext
{
    public function __construct(private LinkResolvers $resolvers) {}

    public function __invoke(Model $post): ?ResponseData
    {
        $kind = PostType::of($post);

        if ($kind === null) {
            return null;
        }

        $url = (string) $post->response_url;
        $path = Links::internalPath($url);
        $preview = $path === null ? null : $this->resolvers->resolve($path)?->toArray();

        // One or the other, never both: a target of mine is drawn as its own
        // card, and naming the host as well would be my own address twice.
        $host = $preview === null ? Links::host($url) : null;

        return new ResponseData(
            kind: $kind->value,
            // An RSVP's answer is its verb, so it replaces the label rather
            // than being appended to one.
            label: $post->rsvp_value?->verb() ?? $kind->label(),
            property: $kind->property(),
            url: $url,
            title: self::name($kind, $preview, $host, $url, $post->response_title),
            rsvp: $post->rsvp_value?->value,
            host: $host,
            favicon: $host === null ? null : Links::faviconUrl($host),
            preview: $preview,
        );
    }

    /**
     * What to call the target.
     *
     * A note of mine has none: its card title is its opening words, which read
     * as a quotation of something nobody said, so it is named by what it is.
     * Everything else of mine has a real one.
     *
     * Somebody else's is whatever FetchResponseTitle read off the page. Until
     * that comes back, or when it finds nothing usable, all we honestly know is
     * that it is a post, or an event if you are RSVPing to it. The host is not
     * part of the name: it is said after it, and only the name is the p-name.
     *
     * @param  array<string, mixed>|null  $preview
     */
    private static function name(ResponseKind $kind, ?array $preview, ?string $host, string $url, ?string $fetched): string
    {
        if ($preview !== null) {
            return ($preview['type'] ?? null) === TimelineType::Note->value ? 'a note' : $preview['title'];
        }

        if (filled($fetched)) {
            return $fetched;
        }

        if ($host === null) {
            return $url;
        }

        return $kind === ResponseKind::Rsvp ? 'an event' : 'a post';
    }
}
