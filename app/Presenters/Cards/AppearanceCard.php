<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\MediaData;
use App\Enums\TimelineType;
use App\Models\Appearance;
use App\Support\Text;

/**
 * Builds the timeline card for an Appearance: a sentence naming the show, the
 * description as its summary, and the media payload for the inline player.
 */
final class AppearanceCard
{
    /**
     * Kinds where I was the one presenting. Everything else, including a kind
     * added later, is me as a guest on someone's show.
     *
     * @var list<string>
     */
    private const SPOKEN = ['talk', 'workshop'];

    public function present(Appearance $model): CardData
    {
        return new CardData(
            type: $this->type(),
            title: $this->title($model),
            titleLabel: null,
            subtitle: $this->sentence($model),
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
            summary: Text::prose($model->description),
        );
    }

    /** My description where I wrote one, else the sentence naming the show. */
    public function description(Appearance $model): string
    {
        return Text::prose($model->description) ?? (string) $this->sentence($model);
    }

    /** "I spoke at Laracon EU." for a talk or workshop, "I appeared on WP Builds." for anything else. */
    private function sentence(Appearance $model): ?string
    {
        if (! $model->show_name) {
            return null;
        }

        return in_array($model->type, self::SPOKEN, true)
            ? "I spoke at {$model->show_name}."
            : "I appeared on {$model->show_name}.";
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
