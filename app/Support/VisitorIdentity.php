<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Stable, anonymous keys for "the same visitor", in the two shapes the
 * interaction tables need.
 *
 * Both are derived from the request IP alone, deliberately: anything the
 * client sends (a generated token, the user agent) can be varied at will, so
 * folding it in would let one machine mint unlimited identities. An IP is the
 * cheapest thing a visitor cannot trivially rotate. The trade is that a shared
 * connection reads as one person, which on a personal site is the right way
 * round. This only holds because no proxy is trusted (see bootstrap/app.php).
 */
final class VisitorIdentity
{
    /**
     * For counting one vote per visitor per thing. Scoped to the target as
     * well, so the stored hashes cannot be lined up across entries to
     * reconstruct one person's browsing.
     */
    public static function onTarget(Request $request, Model $target): string
    {
        return self::hash([$request->ip(), $target::class, $target->getKey()]);
    }

    /**
     * For recognising a commenter who has been approved before, which has to
     * work across entries and so is not scoped to one.
     */
    public static function reputation(Request $request): string
    {
        return self::hash([$request->ip()]);
    }

    /**
     * @param  list<mixed>  $parts
     */
    private static function hash(array $parts): string
    {
        return hash_hmac('sha256', implode('|', $parts), (string) config('app.key'));
    }
}
