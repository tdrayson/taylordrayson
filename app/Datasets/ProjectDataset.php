<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Project;
use App\Presenters\Cards\ProjectCard;
use App\Timeline\Taxonomies;

/**
 * Side projects and things built.
 */
final class ProjectDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Project;
    }

    public function model(): string
    {
        return Project::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Writing;
    }

    public function icon(): string
    {
        return 'RocketIcon';
    }

    public function label(): string
    {
        return 'Project';
    }

    public function plural(): string
    {
        return 'Projects';
    }

    public function slug(): string
    {
        return 'projects';
    }

    public function keywords(): string
    {
        return 'build side product';
    }

    public function card(): ProjectCard
    {
        return new ProjectCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Project'],
            'stage' => ['label' => 'Stage', 'dataType' => 'enum', 'column' => 'stage', 'category' => 'Project'],
            'description' => ['label' => 'Description', 'dataType' => 'text', 'column' => 'description', 'category' => 'Project'],
            'long_description' => ['label' => 'Long description', 'dataType' => 'text', 'column' => 'long_description', 'category' => 'Project'],
            'photos' => ['label' => 'Photos', 'dataType' => 'media', 'column' => null, 'category' => 'Media', 'suffix' => 'photos'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['title', 'description', 'stage'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::tags(fn (string $label): string => "Projects tagged {$label}");
    }

    public function draftable(): bool
    {
        return true;
    }
}
