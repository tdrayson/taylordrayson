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
    private ?ResponseData $response = null;

    public function present(Note $model): CardData
    {
        $response = $this->response($model);
        $gesture = $response !== null && (bool) $model->responseKind()?->isGesture();

        return new CardData(
            type: $this->type(),
            icon: 'message-circle',
            title: $this->title($model),
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
     * A gesture wrote nothing, so it is named by what was done and to what,
     * where another note is named by its opening words. A photo caption reads
     * this too, which is why the wording lives here and not in present().
     *
     * An RSVP takes its verb from its answer: "I'm going to" says more than
     * "I RSVP'd to", and the answer is the whole point of one.
     */
    public function title(Note $model): string
    {
        $response = $this->response($model);

        if ($response === null || ! $model->responseKind()?->isGesture()) {
            return Str::limit(PortableText::plainText($model->content), 80);
        }

        $sentence = $model->responseKind()?->sentence()
            ?? $model->rsvp_value?->sentence()
            ?? 'I responded to';

        return $sentence.' '.$response->fullTitle();
    }

    public function type(): TimelineType
    {
        return TimelineType::Note;
    }

    /** Held between present() and title(), which both want the same one. */
    private function response(Note $model): ?ResponseData
    {
        return $this->response ??= app(BuildResponseContext::class)($model);
    }
}
