<?php

namespace App\Presenters;

use App\Enums\TimelineType;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Checkin;
use App\Models\Concerns\Timelineable;
use App\Models\Event;
use App\Models\Podcast;

/**
 * The four fields a gallery tile needs off an entry: caption, date, accent and
 * permalink.
 *
 * A full CardData costs far more than this. A check-in shapes every one of its
 * photos into a PhotoData and resolves two map renders before the gallery
 * discards all of it, and check-ins own most of the site's photos.
 */
final class PhotoCaption
{
    /**
     * @return array{caption: string, date: string, accent: string, url: string}
     */
    public static function for(Timelineable $model): array
    {
        return [
            'caption' => self::title($model),
            'date' => $model->occurred_at->format('j M Y'),
            'accent' => TimelineType::for($model)->accent(),
            'url' => $model->url(),
        ];
    }

    /**
     * The card title, mirroring the matching App\Presenters\Cards class. Keep
     * the two in step: a caption that drifts from its card reads as a
     * different entry.
     *
     * Only the types that actually own gallery photos are spelled out. Anything
     * else builds its card: correct by construction, and never hot, since a
     * type absent from this match owns no photographs.
     */
    private static function title(Timelineable $model): string
    {
        return match (true) {
            $model instanceof Checkin => $model->event_name
                ? "{$model->event_name} at {$model->venue_name}"
                : "at {$model->venue_name}",
            $model instanceof Activity => $model->name ?? ucfirst($model->type),
            $model instanceof Event => $model->name,
            $model instanceof Article, $model instanceof Podcast => $model->title,
            default => CardPresenter::for($model)->title,
        };
    }
}
