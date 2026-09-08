<?php

namespace App\Presenters\Cards;

use App\Actions\BuildLinkFavicons;
use App\Actions\BuildLinkPreviews;
use App\Actions\BuildResponseContext;
use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Data\ResponseData;
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
        $response = app(BuildResponseContext::class)($model);
        $gesture = $response !== null && (bool) $model->responseKind()?->isGesture();

        return new CardData(
            type: TimelineType::Note,
            icon: 'message-circle',
            title: $gesture ? self::gestureTitle($model, $response) : Str::limit(PortableText::plainText($model->content), 80),
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
                previews: app(BuildLinkPreviews::class)($model->content),
                favicons: (new BuildLinkFavicons)($model->content),
                // The card names the target in its own title when there is
                // nothing else on it, so the context card would say it twice.
                response: $response === null ? null : [...$response->toArray(), 'namedInTitle' => $gesture],
            ),
        );
    }

    /**
     * A gesture wrote nothing, so its card says what was done and to what,
     * where another note would show its opening words.
     *
     * An RSVP takes its verb from its answer: "I'm going to" says more than
     * "I RSVP'd to", and the answer is the whole point of one.
     */
    private static function gestureTitle(Note $model, ResponseData $response): string
    {
        $sentence = $model->responseKind()?->sentence()
            ?? $model->rsvp_value?->sentence()
            ?? 'I responded to';

        return $sentence.' '.$response->fullTitle();
    }
}
