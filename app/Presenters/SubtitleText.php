<?php

namespace App\Presenters;

use App\Data\SubtitleToken;
use App\Support\Distance;
use App\Support\Units;

/**
 * Writes subtitle tokens out as plain text, the way FeedItem.vue composes them
 * in real units, so a card's string and its tokens can never drift apart.
 */
final class SubtitleText
{
    /**
     * The first token takes no separator, the rest take their own, and empty ones drop out.
     *
     * @param  list<SubtitleToken>  $tokens
     */
    public static function for(array $tokens): string
    {
        $parts = [];

        foreach ($tokens as $token) {
            $text = (string) self::text($token->toArray());

            if ($text !== '') {
                $parts[] = ['text' => $text, 'sep' => $token->sep ?? ', '];
            }
        }

        return implode('', array_map(
            fn (array $part, int $index): string => ($index === 0 ? '' : $part['sep']).$part['text'],
            $parts,
            array_keys($parts),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function text(array $data): ?string
    {
        return match ($data['t']) {
            'dist' => Distance::miles($data['m'], $data['p']).' mi',
            'wt' => number_format($data['kg'], $data['p']).' kg',
            'dur' => ($data['u'] ?? null) === 'minutes'
                ? number_format(intdiv($data['s'], 60)).' minutes'
                : Units::humanDuration($data['s']),
            'kcal' => number_format($data['kcal']).' '.($data['u'] ?? 'kcal'),
            'gbp' => '£'.number_format($data['gbp'], 2),
            'ppl' => Units::pencePerLitre($data['ppl']).'/L',
            default => $data['v'],
        };
    }
}
