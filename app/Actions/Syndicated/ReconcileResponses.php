<?php

namespace App\Actions\Syndicated;

use App\Actions\Webmentions\StoreAuthorPhoto;
use App\Data\SyndicatedResponseData;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\SyndicatedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Writes what a source just reported for one entry, in step with what is
 * already stored.
 *
 * Two rules, because the sources give two kinds of thing. Prose has an id, so
 * it is matched on that and an edit lands on the row it belongs to. A gesture
 * has no id worth trusting: Strava's kudos list is a list of people, and two of
 * the same name are two humans, so the fetched list replaces the stored set for
 * that kind outright.
 */
final class ReconcileResponses
{
    public function __construct(private readonly StoreAuthorPhoto $storePhoto) {}

    /**
     * @param  list<SyndicatedResponseData>  $responses  Everything this source holds for this entry.
     */
    public function __invoke(Model $target, Source $source, array $responses): void
    {
        $incoming = collect($responses);

        $this->replaceGestures($target, $source, $incoming);
        $this->upsertProse($target, $source, $incoming);
    }

    /**
     * @param  Collection<int, SyndicatedResponseData>  $incoming
     */
    private function replaceGestures(Model $target, Source $source, Collection $incoming): void
    {
        $gestures = $incoming->reject(fn (SyndicatedResponseData $data): bool => $this->isProse($data));

        // Kinds absent from the payload are cleared too: a repost withdrawn
        // leaves no row to compare against, only a missing one.
        foreach (WebmentionKind::cases() as $kind) {
            if ($this->isProseKind($kind)) {
                continue;
            }

            $this->rowsFor($target, $source)->where('kind', $kind)->delete();
        }

        foreach ($gestures as $data) {
            $this->write($target, $source, $data);
        }
    }

    /**
     * @param  Collection<int, SyndicatedResponseData>  $incoming
     */
    private function upsertProse(Model $target, Source $source, Collection $incoming): void
    {
        $prose = $incoming->filter(fn (SyndicatedResponseData $data): bool => $this->isProse($data));

        $keptIds = $prose->pluck('sourceId')->filter()->all();

        $stale = $this->rowsFor($target, $source)->where('kind', WebmentionKind::Reply);

        // An empty payload means every stored one has gone, which whereNotIn on
        // an empty list would not say.
        if ($keptIds !== []) {
            $stale->whereNotIn('source_id', $keptIds);
        }

        $stale->delete();

        foreach ($prose as $data) {
            $this->write($target, $source, $data);
        }
    }

    private function write(Model $target, Source $source, SyndicatedResponseData $data): void
    {
        $attributes = $data->attributes();

        $attributes['author_photo_path'] = ($this->storePhoto)($data->authorPhotoUrl);

        $row = $data->sourceId === null
            ? new SyndicatedResponse(['source' => $source->value])
            : SyndicatedResponse::query()->firstOrNew([
                'source' => $source->value,
                'source_id' => $data->sourceId,
            ]);

        $row->fill($attributes);
        $row->target()->associate($target);
        $row->save();
    }

    private function isProse(SyndicatedResponseData $data): bool
    {
        return $this->isProseKind($data->kind);
    }

    private function isProseKind(WebmentionKind $kind): bool
    {
        return $kind === WebmentionKind::Reply;
    }

    /** @return Builder<SyndicatedResponse> */
    private function rowsFor(Model $target, Source $source)
    {
        return SyndicatedResponse::query()
            ->where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())
            ->where('source', $source->value);
    }
}
