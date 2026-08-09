<?php

namespace App\Support;

/**
 * Picks Open Graph card headlines from the pools in config/og-phrases.php. The
 * choice is seeded by the entity plus OG_VERSION, so a card reads the same until
 * that version is bumped, which rerolls every card at once.
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
