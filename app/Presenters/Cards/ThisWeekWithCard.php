<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\MediaData;
use App\Enums\TimelineType;
use App\Models\ThisWeekWith;
use App\Support\Text;

/**
 * Builds the timeline card for a This Week With episode: topic as the summary and
 * the audio/video/thumbnail media payload for the inline player.
 */
final class ThisWeekWithCard
{
    public function present(ThisWeekWith $model): CardData
    {
        return new CardData(
            type: $this->type(),
            title: $this->title($model),
            titleLabel: null,
            subtitle: null,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::media(MediaData::withoutSrcset(
                id: $model->id,
                title: $model->title,
                audioUrl: $model->audio_url,
                videoUrl: $model->video_url,
                // Wide art fronts the card, square art goes to the audio player.
                thumbnail: $model->wideArtworkSrc() ?? $model->squareArtworkSrc(),
                audioCover: $model->squareArtworkSrc() ?? $model->wideArtworkSrc(),
                duration: $model->duration,
                url: $model->url(),
            )),
            summary: Text::prose($model->topic),
        );
    }

    /** The episode's topic line; empty without one, so the title stands in. */
    public function description(ThisWeekWith $model): string
    {
        return (string) Text::prose($model->topic);
    }

    public function title(ThisWeekWith $model): string
    {
        return $model->title;
    }

    public function type(): TimelineType
    {
        return TimelineType::ThisWeekWith;
    }
}
