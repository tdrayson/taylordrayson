<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A deliberately small blocklist guarding the public leaderboard against the
 * most obvious slurs and abuse. It normalises common letter/number swaps before
 * matching so trivial obfuscations (e.g. "n1gger") are still caught. This is a
 * best-effort filter, not a guarantee, overt cases only.
 */
class ProfanityFilter
{
    /** @var list<string> */
    private const BLOCKED = [
        'nigger', 'nigga', 'faggot', 'retard', 'cunt', 'rape',
        'kike', 'spic', 'chink', 'tranny', 'paki', 'coon',
    ];

    /** @var array<string, string> */
    private const SUBSTITUTIONS = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's',
    ];

    public static function contains(string $value): bool
    {
        $normalised = str_replace(
            array_keys(self::SUBSTITUTIONS),
            array_values(self::SUBSTITUTIONS),
            Str::lower($value),
        );

        $collapsed = preg_replace('/[^a-z]/', '', $normalised) ?? '';

        foreach (self::BLOCKED as $word) {
            if (str_contains($collapsed, $word)) {
                return true;
            }
        }

        return false;
    }
}
