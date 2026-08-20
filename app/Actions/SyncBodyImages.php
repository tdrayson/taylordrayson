<?php

namespace App\Actions;

use App\Data\FieldData;
use App\Support\PendingUploads;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Turn the images an author dropped into a document into real attachments.
 *
 * While the editor is open an image points at its parked upload, which is the
 * only URL that exists before the entry does. Once there is something to own it
 * the file moves into the entry's `body` collection and the document is
 * rewritten to the permanent URL.
 */
class SyncBodyImages
{
    private const COLLECTION = 'body';

    /**
     * @param  list<FieldData>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed> The values, with parked URLs replaced.
     */
    public function __invoke(Model $model, array $fields, array $values): array
    {
        if (! $model instanceof HasMedia) {
            return $values;
        }

        foreach ($fields as $field) {
            if (! $field->type->isBody() || ! is_array($values[$field->name] ?? null)) {
                continue;
            }

            $values[$field->name] = $this->rewrite($model, $values[$field->name]);
        }

        return $values;
    }

    /**
     * @param  array<int, mixed>  $document
     * @return array<int, mixed>
     */
    private function rewrite(Model&HasMedia $model, array $document): array
    {
        return array_map(function ($node) use ($model) {
            if (is_array($node) && ($node['children'] ?? null)) {
                $node['children'] = $this->rewrite($model, $node['children']);
            }

            if (! is_array($node) || ($node['_type'] ?? null) !== 'image') {
                return $node;
            }

            $token = $this->tokenIn($node['url'] ?? '');

            if ($token === null) {
                return $node;
            }

            $path = PendingUploads::path($token);

            if ($path === null) {
                return $node;
            }

            $media = $model->addMedia($path)->toMediaCollection(self::COLLECTION);
            PendingUploads::forget($token);

            $node['url'] = $media->getUrl();

            return $node;
        }, $document);
    }

    /** The parked-upload token a URL points at, if it points at one. */
    private function tokenIn(string $url): ?string
    {
        return preg_match('#/media/pending/([A-Za-z0-9]{40})$#', $url, $matches) === 1
            ? $matches[1]
            : null;
    }
}
