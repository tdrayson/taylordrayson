<?php

namespace App\Models\Concerns;

use App\Content\EntryFileRepository;
use App\Content\UrlSlugAllocator;
use Illuminate\Support\Str;

trait HasFlatFile
{
    public static function bootHasFlatFile(): void
    {
        static::saving(function (self $model): void {
            if (blank($model->getAttribute('ulid'))) {
                $model->setAttribute('ulid', (string) Str::ulid());
            }

            $repository = app(EntryFileRepository::class);

            if ($model->exists && $model->flatFilePathIsDirty()) {
                $repository->deleteOriginal($model);
            }

            $repository->write($model);
        });

        static::deleted(function (self $model): void {
            app(EntryFileRepository::class)->delete($model);
        });
    }

    public function flatFileExtension(): string
    {
        return 'md';
    }

    /**
     * Attributes that affect the on-disk path (date folder and/or filename).
     *
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at', 'slug'];
    }

    public function flatFilePathIsDirty(): bool
    {
        foreach ($this->flatFilePathAttributes() as $attribute) {
            if ($this->isDirty($attribute)) {
                return true;
            }
        }

        return false;
    }

    public function flatFileSlug(bool $original = false): string
    {
        if ($original && method_exists($this, 'timelineEntry')) {
            $this->loadMissing('timelineEntry');

            // During saving the spine still holds the previous URL slug, which
            // is the filename currently on disk.
            if (filled($this->timelineEntry?->url_slug)) {
                return (string) $this->timelineEntry->url_slug;
            }
        }

        if (method_exists($this, 'flatFileDirectory')) {
            return $this->flatFileBaseSlug($original);
        }

        return app(UrlSlugAllocator::class)->allocate($this, $original);
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        if ($original) {
            $slug = $this->getOriginal('slug');

            return filled($slug) ? (string) $slug : $this->flatFileType();
        }

        if (method_exists($this, 'slug')) {
            return $this->slug();
        }

        $slug = $this->getAttribute('slug');

        return filled($slug) ? (string) $slug : $this->flatFileType();
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        return [];
    }

    abstract public function flatFileType(): string;

    abstract public function flatFileBody(): string;
}
