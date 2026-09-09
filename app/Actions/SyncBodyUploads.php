<?php

namespace App\Actions;

use App\Data\FieldData;
use App\Support\PendingUploads;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Turn the images and files an author dropped into a document into real
 * attachments.
 *
 * While the editor is open an upload points at its parked file, which is the
 * only URL that exists before the entry does. Once there is something to own it
 * the file moves into the entry's `body` collection and the document is
 * rewritten to the permanent URL.
 */
class SyncBodyUploads
{
    private const COLLECTION = 'body';

    /**
     * Node types that carry an upload. A `file` node sourced from GitHub names a
     * release asset instead of a URL, so it never matches and is left alone.
     *
     * @var list<string>
     */
    private const UPLOAD_TYPES = ['image', 'file'];

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

        $edited = false;

        foreach ($fields as $field) {
            if (! $field->type->isBody() || ! is_array($values[$field->name] ?? null)) {
                continue;
            }

            $values[$field->name] = $this->rewrite($model, $values[$field->name]);
            $edited = true;
        }

        // Absent means "not edited", which is not the same as a document that
        // no longer mentions an upload: an update touching one field must not
        // delete what it never sent.
        if ($edited) {
            $this->detachRemoved($model, $values, $fields);
        }

        return $values;
    }

    /**
     * Delete any body attachment the document no longer points at.
     *
     * Attaching without this leaks: an image removed in the editor keeps both
     * its attachment row and its file, and nothing else ever cleans them up.
     *
     * @param  array<string, mixed>  $values
     * @param  list<FieldData>  $fields
     */
    private function detachRemoved(Model&HasMedia $model, array $values, array $fields): void
    {
        $referenced = [];

        foreach ($fields as $field) {
            if ($field->type->isBody() && is_array($values[$field->name] ?? null)) {
                $this->collectUrls($values[$field->name], $referenced);
            }
        }

        foreach ($model->refresh()->getMedia(self::COLLECTION) as $media) {
            if (! in_array($media->getUrl(), $referenced, true)) {
                $media->delete();
            }
        }
    }

    /**
     * Every attachment URL in a document, however deeply nested.
     *
     * @param  array<int, mixed>  $document
     * @param  list<string>  $urls
     */
    private function collectUrls(array $document, array &$urls): void
    {
        foreach ($document as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (in_array($node['_type'] ?? null, self::UPLOAD_TYPES, true) && is_string($node['url'] ?? null)) {
                $urls[] = $node['url'];
            }

            if (is_array($node['children'] ?? null)) {
                $this->collectUrls($node['children'], $urls);
            }
        }
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

            if (! is_array($node) || ! in_array($node['_type'] ?? null, self::UPLOAD_TYPES, true)) {
                return $node;
            }

            $token = $this->tokenIn(is_string($node['url'] ?? null) ? $node['url'] : '');

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

            if ($node['_type'] === 'file') {
                // The card is labelled from these, and only the stored media
                // knows what the file ended up being called.
                $node['name'] = $media->file_name;
                $node['mime'] = $media->mime_type;
                $node['size'] = $media->size;
            }

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
