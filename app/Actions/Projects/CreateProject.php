<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Support\EntryInstant;
use Illuminate\Support\Str;

class CreateProject
{
    /**
     * `status` is the project's real-world state (active, shipped, archived),
     * not a publish gate, so a project has no draft state and appears on the
     * timeline as soon as it exists.
     *
     * @param  array{title: string, slug?: string|null, description?: string|null, long_description?: array<int, mixed>|null, url?: string|null, github_url?: string|null, status?: string, featured?: bool, occurred_at?: string|null, tags?: list<string>}  $attributes
     */
    public function __invoke(array $attributes): Project
    {
        $slug = $attributes['slug'] ?? null;

        $project = Project::create([
            'title' => $attributes['title'],
            'slug' => $slug !== null && $slug !== '' ? $slug : Str::slug($attributes['title']),
            'description' => $attributes['description'] ?? null,
            'long_description' => $attributes['long_description'] ?? null,
            'url' => $attributes['url'] ?? null,
            'github_url' => $attributes['github_url'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'featured' => $attributes['featured'] ?? false,
            'occurred_at' => $attributes['occurred_at'] ?? EntryInstant::nowLocal(),
        ]);

        if (array_key_exists('tags', $attributes)) {
            $project->syncTagNames($attributes['tags']);
        }

        return $project;
    }
}
