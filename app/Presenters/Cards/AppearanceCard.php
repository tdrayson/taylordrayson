<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\MediaData;
use App\Enums\TimelineType;
use App\Models\Appearance;

/**
 * Builds the timeline card for an Appearance: show name as the subtitle and
 * the audio/video/thumbnail media payload for the inline player.
 */
final class AppearanceCard
{
    public function present(Appearance $model): CardData
    {
        return new CardData(
            type: $this->type(),
            title: $this->title($model),
            titleLabel: null,
            subtitle: $model->show_name ? "I spoke at {$model->show_name}." : null,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::media(MediaData::withSrcset(
                id: "appearance-{$model->id}",
                title: $model->title,
                audioUrl: $model->audio_url,
                videoUrl: $model->video_url,
                thumbnail: $model->thumbnailUrl(),
                srcset: $model->thumbnailSrcset(),
                duration: $model->duration,
                url: $model->url(),
            )),
        );
    }

    public function title(Appearance $model): string
    {
        return $model->title;
    }

    public function type(): TimelineType
    {
        return TimelineType::Appearance;
    }
}
