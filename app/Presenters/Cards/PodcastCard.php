<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\MediaData;
use App\Enums\TimelineType;
use App\Models\Podcast;

/**
 * Builds the timeline card for a Podcast episode: topic as the subtitle and
 * the audio/video/thumbnail media payload for the inline player.
 */
final class PodcastCard
{
    public function present(Podcast $model): CardData
    {
        return new CardData(
            type: TimelineType::Podcast,
            icon: 'headphones',
            title: $model->title,
            titleLabel: null,
            subtitle: $model->topic,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'podcast',
            range: null,
            meta: CardMeta::media(MediaData::withoutSrcset(
                id: $model->id,
                title: $model->title,
                audioUrl: $model->audio_url,
                videoUrl: $model->video_url,
                thumbnail: $model->cover_image ?? $model->thumbnail,
                duration: $model->duration,
                url: $model->url(),
            )),
        );
    }
}
