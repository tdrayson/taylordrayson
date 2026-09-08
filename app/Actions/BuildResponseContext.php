<?php

namespace App\Actions;

use App\Actions\Mentions\ResolveInternalTarget;
use App\Data\ResponseData;
use App\Enums\ResponseKind;
use App\Support\EntryName;
use App\Support\Links;
use App\Support\PostType;
use Illuminate\Database\Eloquent\Model;

/**
 * The reply context for a post that responds to something, or null for one
 * that does not.
 */
class BuildResponseContext
{
    public function __construct(private ResolveInternalTarget $resolve) {}

    public function __invoke(Model $post): ?ResponseData
    {
        $kind = PostType::of($post);

        if ($kind === null) {
            return null;
        }

        $url = (string) $post->response_url;
        $path = Links::internalPath($url);
        $target = $path === null ? null : ($this->resolve)($path);

        // One or the other, never both: an entry of mine is named as mine, and
        // saying my own address after it says nothing a reader here needs.
        $host = $target === null ? Links::host($url) : null;

        return new ResponseData(
            kind: $kind->value,
            // An RSVP's answer is its verb, so it replaces the label rather
            // than being appended to one.
            label: $post->rsvp_value?->verb() ?? $kind->label(),
            property: $kind->property(),
            url: $url,
            title: self::name($kind, $target, $host, $url, $post->response_title),
            rsvp: $post->rsvp_value?->value,
            host: $host,
            favicon: $host === null ? null : Links::faviconUrl($host),
            internal: $target !== null,
        );
    }

    /**
     * What to call the target.
     *
     * One of mine is named by EntryName, which knows that some of my types
     * have real titles and some only have card copy.
     *
     * Somebody else's is whatever FetchResponseTitle read off the page. Until
     * that comes back, or when it finds nothing usable, all we honestly know is
     * that it is a post, or an event if you are RSVPing to it. The host is not
     * part of the name: it is said after it, and only the name is the p-name.
     */
    private static function name(ResponseKind $kind, ?Model $target, ?string $host, string $url, ?string $fetched): string
    {
        if ($target !== null) {
            return EntryName::for($target, possessive: true);
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
