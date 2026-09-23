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
use Illuminate\Support\Facades\DB;

/**
 * Writes what a source just reported for one entry, in step with what is stored.
 * Prose is matched on its id; a gesture has none, so the list replaces the set.
 */
final class ReconcileResponses
{
    public function __construct(private readonly StoreAuthorPhoto $storePhoto) {}

    /**
     * @param  list<SyndicatedResponseData>  $responses  Everything this source holds for this entry.
     * @param  list<WebmentionKind>  $unvouchedKinds  Kinds whose absence from $responses proves nothing, because
     *                                                the payload was partial. Their stored rows survive untouched.
     */
    public function __invoke(Model $target, Source $source, array $responses, array $unvouchedKinds = []): void
    {
        $incoming = collect($responses);

        // Delete-then-rewrite spans a third-party fetch per row (StoreAuthorPhoto),
        // so a failure partway through must not leave the entry's responses gone.
        DB::transaction(function () use ($target, $source, $incoming, $unvouchedKinds): void {
            $this->replaceGestures($target, $source, $incoming, $unvouchedKinds);
            $this->upsertProse($target, $source, $incoming, $unvouchedKinds);
        });
    }

    /**
     * A gesture has no id, so the stored set can only be replaced wholesale.
     * An unvouched kind therefore has to be left alone entirely: rewriting
     * what we did receive without clearing first would double it.
     *
     * @param  Collection<int, SyndicatedResponseData>  $incoming
     * @param  list<WebmentionKind>  $unvouchedKinds
     */
    private function replaceGestures(Model $target, Source $source, Collection $incoming, array $unvouchedKinds): void
    {
        $gestures = $incoming->reject(fn (SyndicatedResponseData $data): bool => $this->isProse($data));
        $mine = $this->markedMine($target, $source);

        // Kinds absent from the payload are cleared too: a repost withdrawn
        // leaves no row to compare against, only a missing one.
        foreach (WebmentionKind::cases() as $kind) {
            if ($this->isProseKind($kind) || in_array($kind, $unvouchedKinds, true)) {
                continue;
            }

            $this->rowsFor($target, $source)->where('kind', $kind)->delete();
        }

        foreach ($gestures as $data) {
            if (in_array($data->kind, $unvouchedKinds, true)) {
                continue;
            }

            // A gesture has no id of its own, so a mark made by hand can only
            // be carried across the rewrite by who left it.
            $carried = in_array([$data->kind->value, $data->authorName], $mine, true);

            $this->write($target, $source, $data->mine || $carried ? $data->asMine() : $data);
        }
    }

    /**
     * The kind and author of every gesture on this target already marked mine.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function markedMine(Model $target, Source $source): array
    {
        return $this->rowsFor($target, $source)
            ->where('mine', true)
            ->get(['kind', 'author_name'])
            ->map(fn (SyndicatedResponse $row): array => [$row->kind->value, $row->author_name])
            ->all();
    }

    /**
     * Prose is keyed on the source's own id, so a partial payload can still be
     * written row by row. Only the deletion has to be held back: absence from a
     * truncated page means "not on this page", not "withdrawn".
     *
     * @param  Collection<int, SyndicatedResponseData>  $incoming
     * @param  list<WebmentionKind>  $unvouchedKinds
     */
    private function upsertProse(Model $target, Source $source, Collection $incoming, array $unvouchedKinds): void
    {
        $prose = $incoming->filter(fn (SyndicatedResponseData $data): bool => $this->isProse($data));

        if (! in_array(WebmentionKind::Reply, $unvouchedKinds, true)) {
            $this->deleteStaleProse($target, $source, $prose);
        }

        foreach ($prose as $data) {
            $this->write($target, $source, $data);
        }
    }

    /**
     * @param  Collection<int, SyndicatedResponseData>  $prose
     */
    private function deleteStaleProse(Model $target, Source $source, Collection $prose): void
    {
        $keptIds = $prose->pluck('sourceId')->filter()->all();

        $stale = $this->rowsFor($target, $source)->where('kind', WebmentionKind::Reply);

        if ($keptIds !== []) {
            $stale->whereNotIn('source_id', $keptIds);
        }

        $stale->delete();
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
