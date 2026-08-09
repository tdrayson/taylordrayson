<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\MediaType;
use App\Enums\TimelineType;
use App\Models\Media;

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
        $title = $this->showTitle($model) ?? $model->title;

        $detail = match ($model->type) {
            MediaType::Film => $model->meta['year'] ?? null,
            MediaType::TvEpisode => isset($model->meta['season'], $model->meta['episode'])
                ? sprintf('S%02dE%02d', $model->meta['season'], $model->meta['episode'])
                : null,
            MediaType::Book => $model->meta['author'] ?? null,
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

    /**
     * The show a TV episode belongs to, or null for anything else.
     *
     * Reads the denormalised `show_title` before the relation, the reverse of
     * synthesiseSeriesCard: that runs once per collapsed group, this runs for
     * every media row in the feed, so touching `series` first would be an N+1
     * across the timeline. The relation stays as the fallback for a row whose
     * meta predates the key.
     */
    private function showTitle(Media $model): ?string
    {
        if ($model->type !== MediaType::TvEpisode) {
            return null;
        }

        return $model->meta['show_title'] ?? $model->series?->title;
    }
}
