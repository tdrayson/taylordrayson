<?php

namespace App\Presenters\Cards;

use App\Actions\BuildLinkFavicons;
use App\Actions\BuildLinkPreviews;
use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Note;
use App\Support\PortableText;
use Illuminate\Support\Str;

/**
 * Builds the timeline card for a Note: truncated content as the title, the
 * whole Portable Text document as the card body, plus any gallery photos.
 *
 * The body keeps its links because a note is the entry itself, so the feed is
 * showing the thing rather than a summary of it.
 */
final class NoteCard
{
    public function present(Note $model): CardData
    {
        return new CardData(
            type: TimelineType::Note,
            icon: 'message-circle',
            title: Str::limit(PortableText::plainText($model->content), 80),
            titleLabel: null,
            subtitle: null,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'note',
            range: null,
            meta: CardMeta::note(
                body: $model->content,
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['id'], $photo['src'], $photo['srcset'], $photo['full'], $photo['alt'], $photo['caption'], $photo['latitude'], $photo['longitude']),
                    $model->galleryPhotos(),
                ),
                previews: app(BuildLinkPreviews::class)($model->content),
                favicons: (new BuildLinkFavicons)($model->content),
            ),
        );
    }
}
