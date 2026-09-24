<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Stable, anonymous keys for "the same visitor", in the two shapes the
 * interaction tables need.
 */
final class VisitorIdentity
{
    /**
     * For one reaction per browser per thing, from the random token the
     * browser holds. Scoped to the target so rows cannot be joined across entries.
     *
     * @param  string  $token  The browser's reactor token.
     * @param  Model  $target  The entry or page reacted to.
     */
    public static function onTarget(string $token, Model $target): string
    {
        return self::hash([$token, $target::class, $target->getKey()]);
    }

    /**
     * For recognising a commenter who has been approved before, which has to
     * work across entries and so is keyed on the IP and not scoped to one.
     * This only holds because no proxy is trusted (see bootstrap/app.php).
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
