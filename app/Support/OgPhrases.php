<?php

namespace App\Support;

/**
 * Picks Open Graph card headlines from the pools in config/og-phrases.php.
 *
 * The choice is deterministic, seeded by the entity it describes (an entry id, a
 * period, a type) plus the current OG_VERSION, so a given card always reads the
 * same (stable for social caches) until OG_VERSION is bumped, which rerolls every
 * card's wording at once.
 */
class OgPhrases
{
    /**
     * Pick one phrase for the given config key, filling :placeholders.
     *
     * @param  array<string, string|int|float|null>  $replacements  Placeholder name => value.
     * @return string|null The resolved phrase, or null when the key has no phrases.
     */
    public static function pick(string $key, array $replacements = [], string $seed = ''): ?string
    {
        $phrases = config("og-phrases.{$key}");

        if (! is_array($phrases) || $phrases === []) {
            return null;
        }

        $phrase = $phrases[abs(crc32($key.'|'.config('og.version').'|'.$seed)) % count($phrases)];

        foreach ($replacements as $placeholder => $replacement) {
            $phrase = str_replace(":{$placeholder}", (string) $replacement, $phrase);
        }

        return $phrase;
    }
}
