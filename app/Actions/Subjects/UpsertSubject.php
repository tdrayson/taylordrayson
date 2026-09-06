<?php

namespace App\Actions\Subjects;

use App\Models\Subject;
use Illuminate\Support\Str;

class UpsertSubject
{
    /**
     * Create a subject or save one already found by route binding. `kind`
     * settles a new subject and is never revisited afterwards, matching how
     * SubjectFields only ever asks for it once, at creation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(?Subject $subject, array $attributes): Subject
    {
        $subject ??= new Subject(['kind' => $attributes['kind']]);

        $slug = $attributes['slug'] ?? null;

        $subject->fill([
            ...$attributes,
            'slug' => $slug !== null && $slug !== '' ? $slug : Str::slug($attributes['name'] ?? $subject->name),
        ]);

        $subject->save();

        return $subject->refresh();
    }
}
