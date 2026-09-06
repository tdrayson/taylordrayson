<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

trait HasTags
{
    protected static function bootHasTags(): void
    {
        static::deleting(function ($model): void {
            $model->tags()->detach();
        });
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Sync by human-readable names, creating tags on first use.
     *
     * @param  list<string>  $names
     */
    public function syncTagNames(array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => Str::slug($name))
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => self::titleCaseTag($name)],
            )->id);

        $this->tags()->sync($ids->all());
    }

    /**
     * Capitalise a new tag's name, so tags read the same however they were
     * typed. Only all-lowercase words are touched: "TV" and "iOS" are how
     * somebody meant to write them, and title-casing would flatten both.
     */
    private static function titleCaseTag(string $name): string
    {
        return preg_replace_callback(
            "/[\\p{L}\\p{N}']+/u",
            fn (array $word): string => Str::lower($word[0]) === $word[0] ? Str::ucfirst($word[0]) : $word[0],
            $name,
        ) ?? $name;
    }

    /** @return list<string> */
    public function tagNames(): array
    {
        return $this->tags->pluck('name')->all();
    }
}
