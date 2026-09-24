<?php

namespace App\Support;

use Blaspsoft\Blasp\Enums\Severity;
use Blaspsoft\Blasp\Facades\Blasp;

/**
 * The site's two profanity policies, named in one place so the leaderboard and
 * the comment form cannot drift apart.
 *
 * Blasp does the matching. It normalises the evasions a hand-rolled blocklist
 * misses (`n1gger`, `f u c k`, doubled letters, phonetic spellings) without
 * the Scunthorpe problem: Scunthorpe, Penistone, Cockburn and assassin all
 * pass. The judgement left to us is the threshold, and it differs by field.
 */
class ProfanityFilter
{
    /**
     * A public display name, held to `high`.
     *
     * Not `extreme`, which lets "cunt" through onto a leaderboard, and not
     * `moderate`, which would refuse anyone called Dick or Randy.
     */
    public static function blocksName(string $value): bool
    {
        return self::offensive($value, Severity::High);
    }

    /**
     * A comment body, held to `extreme` only.
     *
     * Swearing in a comment is not abuse: "this is fucking brilliant" is a
     * compliment, and a filter that refuses it is worse than no filter. This
     * catches slurs, and the caller routes those to spam rather than telling
     * the sender which word did it.
     */
    public static function isAbusive(string $value): bool
    {
        return self::offensive($value, Severity::Extreme);
    }

    private static function offensive(string $value, Severity $severity): bool
    {
        return trim($value) !== ''
            && Blasp::withSeverity($severity)->check($value)->isOffensive();
    }
}
