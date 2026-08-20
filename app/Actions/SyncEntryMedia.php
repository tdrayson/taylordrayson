<?php

namespace App\Actions;

use App\Data\FieldData;
use App\Models\Attachment as Media;
use App\Support\PendingUploads;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Reconcile an entry's media collections with what the editor sent back.
 *
 * The field's value is an ordered list, each item either the uuid of media
 * already attached or a `pending:` token from an upload made while the form was
 * open. Anything already attached but absent from the list was removed in the
 * editor, so it is detached here.
 */
class SyncEntryMedia
{
    /**
     * @param  list<FieldData>  $fields
     * @param  array<string, mixed>  $values  The raw request values, keyed by field name.
     */
    public function __invoke(Model $model, array $fields, array $values): void
    {
        if (! $model instanceof HasMedia) {
            return;
        }

        foreach ($fields as $field) {
            if (! $field->type->isMedia() || $field->collection === null) {
                continue;
            }

            // Absent means "not edited", which is not the same as an empty list
            // meaning "everything removed": an update that touches one field
            // must not wipe a collection it never sent.
            if (! array_key_exists($field->name, $values)) {
                continue;
            }

            $this->sync($model, $field->collection, array_values((array) ($values[$field->name] ?? [])));
        }
    }

    /**
     * @param  list<string>  $items
     */
    private function sync(Model&HasMedia $model, string $collection, array $items): void
    {
        $keep = array_values(array_filter($items, fn (string $item): bool => ! str_starts_with($item, 'pending:')));

        foreach ($model->getMedia($collection) as $media) {
            if (! in_array($media->uuid, $keep, true)) {
                $media->delete();
            }
        }

        foreach ($items as $position => $item) {
            if (! str_starts_with($item, 'pending:')) {
                continue;
            }

            $token = substr($item, strlen('pending:'));
            $path = PendingUploads::path($token);

            if ($path === null) {
                continue;
            }

            $model->addMedia($path)->toMediaCollection($collection);
            PendingUploads::forget($token);
        }

        $this->reorder($model, $collection, $items);
    }

    /**
     * Put the collection back in the order the editor showed, which is the order
     * the author arranged rather than the order things happened to upload in.
     *
     * @param  list<string>  $items
     */
    private function reorder(Model&HasMedia $model, string $collection, array $items): void
    {
        $kept = array_values(array_filter($items, fn (string $item): bool => ! str_starts_with($item, 'pending:')));
        $positions = array_flip($kept);

        // Anything newly added sorts after everything kept, in arrival order.
        $ids = $model->refresh()->getMedia($collection)
            ->sortBy(fn (Media $media): int => $positions[$media->uuid] ?? count($kept) + $media->id)
            ->pluck('id')
            ->all();

        if ($ids !== []) {
            Media::setNewOrder($ids);
        }
    }
}
