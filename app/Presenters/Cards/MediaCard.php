<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\MediaType;
use App\Enums\TimelineType;
use App\Models\Media;
use App\Support\ShowTitle;

/**
 * Builds the timeline card for a Media entry: rating and a type-specific
 * detail (film year, TV season/episode, book author) as the subtitle.
 *
 * A TV episode is led by its show, not its own title. A Media row's `title` is
 * the episode's, so a lone watch used to read "Home" with no hint it was Ted
 * Lasso. A same-day binge already collapses to a show-titled card in
 * BuildTimelineFeed; this makes the single-episode card agree with it.
 */
final class MediaCard
{
    public function present(Media $model): CardData
    {
        $title = ShowTitle::for($model) ?? $model->title;

        $detail = match ($model->type) {
            MediaType::Film => $model->meta->year,
            MediaType::TvEpisode => $model->meta->season !== null && $model->meta->episode !== null
                ? sprintf('S%02dE%02d', $model->meta->season, $model->meta->episode)
                : null,
            MediaType::Book => $model->meta->author,
            default => null,
        };

        $parts = array_filter([
            $model->rating ? "★ {$model->rating} / 10" : null,
            $detail,
            // The episode title, displaced from the heading by the show name.
            // Skipped when the show could not be resolved, so the card never
            // prints the same text twice.
            $title === $model->title ? null : $model->title,
        ]);

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
}
