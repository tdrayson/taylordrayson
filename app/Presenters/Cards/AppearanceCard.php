<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\MediaData;
use App\Data\SubtitleToken;
use App\Enums\TimelineType;
use App\Models\Appearance;
use App\Presenters\SubtitleText;

/**
 * Builds the timeline card for an Appearance: show name as the subtitle and
 * the audio/video/thumbnail media payload for the inline player.
 */
final class AppearanceCard
{
    public function present(Appearance $model): CardData
    {
        $tokens = $this->tokens($model);

        return new CardData(
            type: $this->type(),
            title: $this->title($model),
            titleLabel: null,
            subtitle: $tokens === [] ? null : SubtitleText::for($tokens),
            subtitleTokens: $tokens === [] ? null : $tokens,
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

    /**
     * Where I spoke, then how long it ran as its own sentence.
     *
     * @return list<SubtitleToken>
     */
    private function tokens(Appearance $model): array
    {
        $tokens = $model->show_name ? [SubtitleToken::text("I spoke at {$model->show_name}.")] : [];

        if ($model->duration) {
            $tokens[] = SubtitleToken::text('It was', ' ');
            $tokens[] = SubtitleToken::dur((int) $model->duration, ' ', 'minutes');
            $tokens[] = SubtitleToken::text('long.', ' ');
        }

        return $tokens;
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
