<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Support\TimelineUrlSlug;
use Illuminate\Validation\ValidationException;

class UpdateProject
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Project $project, array $attributes): Project
    {
        if (array_key_exists('tags', $attributes)) {
            $project->syncTagNames($attributes['tags']);
            unset($attributes['tags']);
        }

        if (! empty($attributes['slug']) && TimelineUrlSlug::isReserved($attributes['slug'])) {
            throw ValidationException::withMessages(['slug' => [TimelineUrlSlug::reservationMessage($attributes['slug'])]]);
        }

        $project->fill($attributes)->save();

        return $project->refresh();
    }
}
