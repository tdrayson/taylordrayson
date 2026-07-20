<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Note;
use Illuminate\Support\Str;

/**
 * Builds the timeline card for a Note: truncated content as the title, full
 * content as the card body, plus any gallery photos.
 */
final class NoteCard
{
    public function present(Note $model): CardData
    {
        return new CardData(
            type: TimelineType::Note,
            icon: 'message-circle',
            title: Str::limit($model->content, 80),
            titleLabel: null,
            subtitle: null,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'note',
            range: null,
            meta: CardMeta::note(
                body: $model->content,
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
                    $model->galleryPhotos(),
                ),
            ),
        );
    }
}
