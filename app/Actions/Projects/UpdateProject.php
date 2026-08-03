<?php

namespace App\Actions\Projects;

use App\Models\Project;

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

        $project->fill($attributes)->save();

        return $project->refresh();
    }
}
