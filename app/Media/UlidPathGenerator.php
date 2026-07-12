<?php

namespace App\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Store media under media/{entry-ulid}/ so paths follow the content identity,
 * not the disposable attachments row id. Falls back to the legacy {id}/ folder
 * when that still holds the files (pre-relocate).
 */
class UlidPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $preferred = 'media/'.$this->entryUlid($media);
        $legacy = (string) $media->getKey();

        if ($preferred === 'media/'.$legacy) {
            return $preferred;
        }

        $disk = Storage::disk($media->disk);

        if ($this->directoryHasFiles($disk, $legacy) && ! $this->directoryHasFiles($disk, $preferred)) {
            return $legacy;
        }

        return $preferred;
    }

    protected function entryUlid(Media $media): string
    {
        $model = $media->model;

        if ($model instanceof Model) {
            $ulid = $model->getAttribute('ulid');

            if (filled($ulid)) {
                return (string) $ulid;
            }

            if (in_array('ulid', $model->getFillable(), true)) {
                $ulid = (string) Str::ulid();
                $model->setAttribute('ulid', $ulid);
                $model->saveQuietly();

                return $ulid;
            }
        }

        return (string) ($media->uuid ?: $media->getKey());
    }

    protected function directoryHasFiles(Filesystem $disk, string $directory): bool
    {
        return $disk->exists($directory) && $disk->allFiles($directory) !== [];
    }
}
