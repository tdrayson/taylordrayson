<?php

namespace App\Support;

use App\Enums\ResponseKind;
use App\Enums\RsvpValue;
use Illuminate\Database\Eloquent\Model;

/**
 * What a microformats parser will call one of my posts.
 *
 * Follows the W3C post type discovery algorithm, which derives a post's type
 * from the properties it carries rather than from a stored type. We do store
 * the kind, so this could read the column and stop; it walks the properties
 * instead, in the algorithm's order, so that what the cards say and what the
 * markup claims are decided by one function and cannot drift apart.
 *
 * The order matters in one place. An RSVP is an in-reply-to that also answers,
 * so the answer is checked first; asking about the property alone would call
 * every RSVP a reply.
 *
 * @see https://www.w3.org/TR/post-type-discovery/
 */
final class PostType
{
    /**
     * The response this post is, or null for one that is only itself: a plain
     * note or article, and every type that has no response columns at all.
     */
    public static function of(Model $post): ?ResponseKind
    {
        $kind = $post->response_kind ?? null;

        if (! $kind instanceof ResponseKind || blank($post->response_url ?? null)) {
            return null;
        }

        // An answer makes it an RSVP whatever else it claims to be, because
        // that is what a parser reading the markup will conclude.
        if (($post->rsvp_value ?? null) instanceof RsvpValue) {
            return ResponseKind::Rsvp;
        }

        // Without one it cannot be: p-rsvp is the property the type is named
        // for, and the algorithm skips a post whose value is missing or unknown.
        return $kind === ResponseKind::Rsvp ? null : $kind;
    }

    /** Whether this post is a response to something at all. */
    public static function isResponse(Model $post): bool
    {
        return self::of($post) !== null;
    }
}
