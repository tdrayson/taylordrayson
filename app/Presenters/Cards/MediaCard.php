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
 */
final class MediaCard
{
    public function present(Media $model): CardData
    {
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
        ]);

        return new CardData(
            type: TimelineType::Media,
            icon: 'film',
            title: $model->title,
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
