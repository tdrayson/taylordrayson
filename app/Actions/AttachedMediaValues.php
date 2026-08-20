<?php

namespace App\Actions;

use App\Data\FieldData;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What an entry's media fields already hold, in the shape the editor posts back:
 * an ordered list per field, each item identified by its media uuid.
 *
 * The uuid is the identifier rather than the id because it is what survives into
 * the form and back without exposing a database key.
 */
class AttachedMediaValues
{
    /**
     * @param  list<FieldData>  $fields
     * @return array<string, list<array{id: string, name: string, url: string}>>
     */
    public function __invoke(Model $model, array $fields): array
    {
        if (! $model instanceof HasMedia) {
            return [];
        }

        $values = [];

        foreach ($fields as $field) {
            if (! $field->type->isMedia() || $field->collection === null) {
                continue;
            }

            $values[$field->name] = $model->getMedia($field->collection)
                ->map(fn (Media $media): array => [
                    'id' => $media->uuid,
                    'name' => $media->name,
                    // The card conversion where there is one: the editor shows
                    // thumbnails, and the original can be many megabytes.
                    'url' => $media->hasGeneratedConversion('card')
                        ? $media->getUrl('card')
                        : $media->getUrl(),
                ])
                ->values()
                ->all();
        }

        return $values;
    }
}
