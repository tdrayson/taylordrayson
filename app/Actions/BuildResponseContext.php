<?php

namespace App\Actions;

use App\Data\ResponseData;
use App\Enums\ResponseKind;
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
            label: $kind->label(),
            property: $kind->property(),
            url: $url,
            title: self::name($kind, $preview, $host, $url),
            rsvp: $post->rsvp_value?->value,
            rsvpLabel: $post->rsvp_value?->label(),
            host: $host,
            favicon: $host === null ? null : Links::faviconUrl($host),
            preview: $preview,
        );
    }

    /**
     * What to call the target.
     *
     * A post of mine has a real title. Somebody else's is only a URL, and no
     * card should print one: "a post on seblog.nl" is how you would say it.
     * What you RSVP to is an event, and calling it a post reads as a mistake.
     *
     * @param  array<string, mixed>|null  $preview
     */
    private static function name(ResponseKind $kind, ?array $preview, ?string $host, string $url): string
    {
        if ($preview !== null) {
            return $preview['title'];
        }

        if ($host === null) {
            return $url;
        }

        $noun = $kind === ResponseKind::Rsvp ? 'an event' : 'a post';

        return "{$noun} on {$host}";
    }
}
