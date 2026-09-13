<?php

namespace App\Actions\Projects;

use App\Enums\EntryStatus;
use App\Enums\ProjectStage;
use App\Models\Project;
use App\Support\TimelineUrlSlug;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProject
{
    /**
     * `stage` is the project's lifecycle (active, maintained, on hold, archived).
     *
     * @param  array{title: string, slug?: string|null, description?: string|null, long_description?: array<int, mixed>|null, url?: string|null, github_url?: string|null, stage?: string, featured?: bool, occurred_at?: string|null, tags?: list<string>, status?: string, password?: string|null}  $attributes
     */
    public function __invoke(array $attributes): Project
    {
        $slug = $attributes['slug'] ?? null;
        $slug = $slug !== null && $slug !== '' ? $slug : Str::slug($attributes['title']);

        if (TimelineUrlSlug::isReserved($slug)) {
            throw ValidationException::withMessages(['slug' => [TimelineUrlSlug::reservationMessage($slug)]]);
        }

        $project = Project::create([
            'title' => $attributes['title'],
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
            'long_description' => $attributes['long_description'] ?? null,
            'url' => $attributes['url'] ?? null,
            'github_url' => $attributes['github_url'] ?? null,
            'stage' => $attributes['stage'] ?? ProjectStage::Active,
            'featured' => $attributes['featured'] ?? false,
            'occurred_at' => $attributes['occurred_at'] ?? null,
            'status' => $attributes['status'] ?? EntryStatus::Published,
            'password' => $attributes['password'] ?? null,
        ]);

        if (array_key_exists('tags', $attributes)) {
            $project->syncTagNames($attributes['tags']);
        }

        return $project;
    }
}
