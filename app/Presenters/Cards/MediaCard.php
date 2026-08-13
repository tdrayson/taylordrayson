<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\MediaType;
use App\Enums\TimelineType;
use App\Models\Media;
use App\Support\ShowTitle;

/**
 * Builds the timeline card for a Media entry: rating and a type-specific detail
 * (film year, TV season/episode, book author) as the subtitle. An episode is
 * titled by the episode, since a day's worth of one show would otherwise repeat
 * the same title down the feed; its show leads the subtitle instead.
 */
final class MediaCard
{
    public function present(Media $model): CardData
    {
        $show = ShowTitle::for($model);
        $rating = $model->rating ? "★ {$model->rating} / 10" : null;

        // Falls back to the show when the episode itself is unnamed, so the card
        // is never headed by nothing.
        $title = $model->title !== '' ? $model->title : ($show ?? '');

        $parts = match ($model->type) {
            MediaType::Film => [$rating, $model->meta->year === null ? null : (string) $model->meta->year],
            MediaType::TvEpisode => [$show, $this->episodeCode($model), $rating],
            MediaType::Book => [$rating, $model->meta->author],
            default => [$rating],
        };

        // Drops a part that merely repeats the title, so the card never prints
        // the same text twice.
        $parts = array_filter($parts, fn (?string $part): bool => $part !== null && $part !== $title);

        return new CardData(
            type: TimelineType::Media,
            icon: 'film',
            title: $title,
            titleLabel: null,
            subtitle: $parts ? implode(', ', $parts) : null,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'media',
            range: null,
            meta: CardMeta::empty(),
        );
    }

    /** The "S01E03" marker, or null when either number is missing. */
    private function episodeCode(Media $model): ?string
    {
        if ($model->meta->season === null || $model->meta->episode === null) {
            return null;
        }

        return sprintf('S%02dE%02d', $model->meta->season, $model->meta->episode);
    }
}
