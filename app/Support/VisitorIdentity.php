<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * A stable, anonymous key for "the same visitor, on this one thing".
 *
 * Derived from the request IP alone, deliberately: anything the client sends
 * (a generated token, the user agent) can be varied at will, so folding it in
 * would let one machine mint unlimited identities and the unique index on
 * `reactions` would stop counting anything. An IP is the cheapest thing a
 * visitor cannot trivially rotate, and it makes the count mean something.
 *
 * The trade is that a shared connection reads as one voter. On a personal site
 * that is the right way round.
 */
final class VisitorIdentity
{
    /**
     * Scoped to the target as well as the visitor, so the stored hashes cannot
     * be lined up across entries to reconstruct one person's browsing.
     */
    public static function for(Request $request, Model $target): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [$request->ip(), $target::class, $target->getKey()]),
            (string) config('app.key'),
        );
    }
}
