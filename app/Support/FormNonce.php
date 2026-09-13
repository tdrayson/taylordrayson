<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A single-use token issued when someone starts filling a form in, and spent
 * when they submit it. Two jobs: a blind POST to the endpoint has no token at
 * all, and the issue time gives the submit a "how long did that take" to
 * check, which is what catches a bot that does fetch one first.
 */
final class FormNonce
{
    private const TTL_SECONDS = 3600;

    public static function issue(string $purpose): string
    {
        $nonce = Str::uuid()->toString();

        Cache::put(self::key($purpose, $nonce), now()->timestamp, self::TTL_SECONDS);

        return $nonce;
    }

    /**
     * Spend a token, returning how many seconds ago it was issued, or null if
     * it never existed, has expired, or has already been spent.
     */
    public static function claim(string $purpose, string $nonce): ?int
    {
        $issuedAt = Cache::pull(self::key($purpose, $nonce));

        return $issuedAt === null ? null : now()->timestamp - (int) $issuedAt;
    }

    private static function key(string $purpose, string $nonce): string
    {
        return "nonce:{$purpose}:{$nonce}";
    }
}
