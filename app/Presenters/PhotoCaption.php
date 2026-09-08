<?php

namespace App\Presenters;

use App\Models\Concerns\Timelineable;

/**
 * The four fields a gallery tile needs off an entry: caption, date, accent and
 * permalink.
 *
 * The title comes from the entry's own card presenter, so a caption cannot
 * drift from the card. What it skips is the rest of the CardData: a check-in
 * shapes every one of its photos into a PhotoData and resolves two map renders
 * that the gallery discards, and check-ins own most of the site's photos.
 */
final class PhotoCaption
{
    /**
     * @return array{caption: string, date: string, accent: string, url: string}
     */
    public static function for(Timelineable $model): array
    {
        $card = CardPresenter::card($model);

        return [
            'caption' => $card->title($model),
            'date' => $model->occurred_at->format('j M Y'),
            'accent' => $card->type()->accent(),
            'url' => $model->url(),
        ];
    }
}
