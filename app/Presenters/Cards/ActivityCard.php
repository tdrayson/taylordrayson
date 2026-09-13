<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Data\SubtitleToken;
use App\Enums\TimelineType;
use App\Models\Activity;
use App\Support\Distance;
use App\Support\Units;
use Illuminate\Support\Str;

/**
 * Builds the timeline card for an Activity: distance/duration/calories (or,
 * for strength sessions, exercise/set/volume) summarised into both a
 * pre-formatted subtitle string and structured tokens for FeedItem.vue.
 */
final class ActivityCard
{
    /**
     * Past-tense verb per activity type, for types where one reads naturally.
     * Anything absent names itself instead ("I did 45m of padel"), so a new
     * Strava type needs no change here.
     */
    private const VERBS = [
        'walk' => 'walked',
        'run' => 'ran',
        'ride' => 'cycled',
        'e-bike-ride' => 'cycled',
        'swim' => 'swam',
        'hike' => 'hiked',
        'workout' => 'worked out',
    ];

    /**
     * Types that are played rather than covered. Their verb takes the sport as
     * its object ("I played padel for 45m"), where the verbs above take the
     * distance ("I walked 3 mi"), so the two cannot share a map.
     *
     * Strava files most racket sessions as `workout`, which names no sport and
     * so still reads "I worked out for 45m".
     *
     * @var list<string>
     */
    private const PLAYED = [
        'padel',
        'table-tennis',
        'tennis',
        'badminton',
        'squash',
        'football',
        'golf',
        'basketball',
    ];

    public function present(Activity $model): CardData
    {
        return new CardData(
            type: $this->type(),
            title: $this->title($model),
            titleLabel: null,
            subtitle: $this->cardSubtitle($model),
            subtitleTokens: $this->subtitleTokens($model),
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::activity(
                polyline: data_get($model->meta, 'polyline'),
                photos: $this->photoData($model),
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
            ),
        );
    }

    /**
     * @return list<PhotoData>
     */
    private function photoData(Activity $model): array
    {
        return array_map(
            fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
            $model->galleryPhotos(),
        );
    }

    /**
     * The session as a sentence, rendered from the same tokens the client uses
     * so the two can never drift. Distance is a token, so the visitor's mi/km
     * toggle still rewrites it in place.
     */
    private function cardSubtitle(Activity $model): ?string
    {
        $tokens = $this->subtitleTokens($model);

        return $tokens === null ? null : $this->render($tokens);
    }

    /**
     * Join tokens the way FeedItem.vue's metaText does: the first takes no
     * separator, the rest take their own, and empty ones drop out.
     *
     * @param  list<SubtitleToken>  $tokens
     */
    private function render(array $tokens): string
    {
        $parts = [];

        foreach ($tokens as $token) {
            $data = $token->toArray();

            $text = match ($data['t']) {
                'dist' => Distance::miles($data['m'], $data['p']).' mi',
                'wt' => number_format($data['kg'], $data['p']).' kg',
                default => $data['v'],
            };

            if ((string) $text !== '') {
                $parts[] = ['text' => $text, 'sep' => $data['sep'] ?? ', '];
            }
        }

        return implode('', array_map(
            fn (array $part, int $index): string => ($index === 0 ? '' : $part['sep']).$part['text'],
            $parts,
            array_keys($parts),
        ));
    }

    /**
     * Distance and weight are emitted as raw tokens (metres/kg) rather than
     * pre-formatted strings, so FeedItem.vue composes them through useFormat()
     * and they react to the visitor's unit toggle.
     *
     * @return list<SubtitleToken>|null
     */
    private function subtitleTokens(Activity $model): ?array
    {
        // Keyed on sets rather than an activity-type allow-list, so new distance
        // types need no change here.
        if (is_array($model->meta['sets'] ?? null)) {
            return $this->strengthTokens($model->meta['sets']);
        }

        $duration = $model->duration ? Units::humanDuration($model->duration) : null;
        $tokens = $this->openingTokens($model, $duration);

        if ($tokens === []) {
            return null;
        }

        if ($model->calories) {
            $tokens[] = SubtitleToken::text('and burned '.number_format($model->calories).' kcal', ' ');
        }

        // The stop rides on its own token because the clause it follows varies,
        // and a distance token's text is composed on the client.
        $tokens[] = SubtitleToken::text('.', '');

        return $tokens;
    }

    /**
     * The verb and what it acts on. A distance activity reads "I ran 3.2 mi in
     * 30m"; one measured only in time reads "I walked for 45m"; anything with
     * no verb of its own falls back to naming itself ("I did 45m of padel").
     *
     * @return list<SubtitleToken>
     */
    private function openingTokens(Activity $model, ?string $duration): array
    {
        if (in_array($model->type, self::PLAYED, true)) {
            return $this->playedTokens($model, $duration);
        }

        $verb = self::VERBS[$model->type] ?? null;

        if ($model->distance) {
            return array_values(array_filter([
                SubtitleToken::text($verb ? "I {$verb}" : 'I covered'),
                SubtitleToken::dist((int) $model->distance, 1, ' '),
                $duration ? SubtitleToken::text("in {$duration}", ' ') : null,
            ]));
        }

        if ($duration === null) {
            return [];
        }

        return $verb !== null
            ? [SubtitleToken::text("I {$verb} for {$duration}")]
            : [SubtitleToken::text(sprintf('I did %s of %s', $duration, str_replace('-', ' ', $model->type)))];
    }

    /**
     * A played sport leads with the sport, so any distance it also recorded
     * trails as its own clause rather than becoming the verb's object.
     *
     * @return list<SubtitleToken>
     */
    private function playedTokens(Activity $model, ?string $duration): array
    {
        $sport = str_replace('-', ' ', $model->type);

        $tokens = [SubtitleToken::text($duration !== null ? "I played {$sport} for {$duration}" : "I played {$sport}")];

        if ($model->distance) {
            $tokens[] = SubtitleToken::text('covering', ', ');
            $tokens[] = SubtitleToken::dist((int) $model->distance, 1, ' ');
        }

        return $tokens;
    }

    /**
     * @param  array<int, array{exercise: string, reps: int, weight: float}>  $sets
     * @return list<SubtitleToken>
     */
    private function strengthTokens(array $sets): array
    {
        $exercises = count(array_unique(array_column($sets, 'exercise')));
        $volume = array_sum(array_map(fn (array $set): float => ($set['reps'] ?? 0) * ($set['weight_kg'] ?? $set['weight'] ?? 0), $sets));

        $tokens = [
            SubtitleToken::text(sprintf(
                'I did %d %s across %d %s',
                $exercises,
                Str::plural('exercise', $exercises),
                count($sets),
                Str::plural('set', count($sets)),
            )),
        ];

        if ($volume > 0) {
            $tokens[] = SubtitleToken::text('and lifted', ' ');
            $tokens[] = SubtitleToken::wt($volume, 0, ' ');
        }

        $tokens[] = SubtitleToken::text('.', '');

        return $tokens;
    }

    public function title(Activity $model): string
    {
        return $model->name ?? ucfirst($model->type);
    }

    public function type(): TimelineType
    {
        return TimelineType::Activity;
    }
}
