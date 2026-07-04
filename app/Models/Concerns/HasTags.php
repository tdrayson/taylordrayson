<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

trait HasTags
{
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
                ['name' => $name],
            )->id);

        $this->tags()->sync($ids->all());
    }

    /** @return list<string> */
    public function tagNames(): array
    {
        return $this->tags->pluck('name')->all();
    }
}
