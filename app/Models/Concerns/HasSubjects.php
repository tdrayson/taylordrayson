<?php

namespace App\Models\Concerns;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * An entry's link to the subjects on it. The relation holds only the direct
 * tags; the answer everything else wants is {@see allSubjects()}.
 */
trait HasSubjects
{
    protected static function bootHasSubjects(): void
    {
        static::deleting(function ($model): void {
            $model->subjects()->detach();
        });
    }

    public function subjects(): MorphToMany
    {
        return $this->morphToMany(Subject::class, 'subjectable');
    }

    /**
     * Everyone on this entry: its own tags plus everyone tagged in its
     * photographs. Derived rather than copied, so untagging the last photograph
     * someone appears in actually removes them from the entry.
     *
     * @return Collection<int, Subject>
     */
    public function allSubjects(): Collection
    {
        $fromPhotos = $this->getMedia('cover')
            ->merge($this->getMedia('photos'))
            ->flatMap(fn ($attachment) => $attachment->subjects);

        return $this->subjects
            ->merge($fromPhotos)
            ->unique('id')
            // Enum instances aren't comparable with <=>, so kind sorts on its scalar value.
            ->sortBy([
                fn (Subject $a, Subject $b): int => $a->kind->value <=> $b->kind->value,
                'name',
            ])
            ->values();
    }
}
