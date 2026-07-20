<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Data\SubtitleToken;
use App\Enums\TimelineType;
use App\Models\Activity;
use App\Support\Distance;
use Illuminate\Support\Str;

/**
 * Builds the timeline card for an Activity: distance/duration/calories (or,
 * for strength sessions, exercise/set/volume) summarised into both a
 * pre-formatted subtitle string and structured tokens for FeedItem.vue.
 */
final class ActivityCard
{
    public function present(Activity $model): CardData
    {
        return new CardData(
            type: TimelineType::Activity,
            icon: 'footprints',
            title: $model->name ?? ucfirst($model->type),
            titleLabel: null,
            subtitle: $this->cardSubtitle($model),
            subtitleTokens: $this->subtitleTokens($model),
            occurredAt: $model->occurred_at,
            accent: 'activity',
            range: null,
            meta: CardMeta::activity(
                polyline: data_get($model->meta, 'polyline'),
                photos: $this->photoData($model),
                map: $model->getFirstMediaUrl('map') ?: null,
                mapDark: $model->getFirstMediaUrl('map_dark') ?: null,
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

    private function cardSubtitle(Activity $model): ?string
    {
        // Data-driven, not a hardcoded cardio type list: strength activities
        // carry sets; everything else describes itself by whatever metrics it
        // recorded, so new distance-based types scale in without an allow-list.
        if (is_array($model->meta['sets'] ?? null)) {
            return $this->strengthSubtitle($model->meta['sets']);
        }

        $parts = [];

        if ($model->distance) {
            $parts[] = Distance::miles($model->distance, 1).' mi';
        }

        if ($model->duration) {
            $parts[] = $this->durationForHumans($model->duration);
        }

        if ($model->calories) {
            $parts[] = number_format($model->calories).' kcal';
        }

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * @param  array<int, array{exercise: string, reps: int, weight: float}>  $sets
     */
    private function strengthSubtitle(array $sets): string
    {
        $exercises = count(array_unique(array_column($sets, 'exercise')));
        $volume = array_sum(array_map(fn (array $set): float => ($set['reps'] ?? 0) * ($set['weight_kg'] ?? $set['weight'] ?? 0), $sets));

        $parts = [
            $exercises.' '.Str::plural('exercise', $exercises),
            count($sets).' '.Str::plural('set', count($sets)),
        ];

        if ($volume > 0) {
            $parts[] = number_format($volume).' kg';
        }

        return implode(', ', $parts);
    }

    /**
     * Structured counterpart to cardSubtitle(): distance/weight are emitted as raw
     * tokens (metres/kg) instead of pre-formatted strings, so FeedItem.vue can
     * compose them through useFormat() and react to the visitor's unit toggle.
     *
     * @return list<SubtitleToken>|null
     */
    private function subtitleTokens(Activity $model): ?array
    {
        // Data-driven (see cardSubtitle): sets => strength; otherwise show
        // whatever metrics exist, so new distance types need no allow-list.
        if (is_array($model->meta['sets'] ?? null)) {
            return $this->strengthTokens($model->meta['sets']);
        }

        $tokens = [];

        if ($model->distance) {
            $tokens[] = SubtitleToken::dist((int) $model->distance, 1);
        }

        if ($model->duration) {
            $tokens[] = SubtitleToken::text($this->durationForHumans($model->duration));
        }

        if ($model->calories) {
            $tokens[] = SubtitleToken::text(number_format($model->calories).' kcal');
        }

        return $tokens ?: null;
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
            SubtitleToken::text($exercises.' '.Str::plural('exercise', $exercises)),
            SubtitleToken::text(count($sets).' '.Str::plural('set', count($sets))),
        ];

        if ($volume > 0) {
            $tokens[] = SubtitleToken::wt($volume, 0);
        }

        return $tokens;
    }

    private function durationForHumans(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes % 60);
        }

        return "{$minutes}m";
    }
}
