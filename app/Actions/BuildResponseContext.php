<?php

namespace App\Actions;

use App\Data\ResponseData;
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
            rsvp: $post->rsvp_value?->value,
            rsvpLabel: $post->rsvp_value?->label(),
            host: $host,
            favicon: $host === null ? null : Links::faviconUrl($host),
            preview: $preview,
        );
    }
}
