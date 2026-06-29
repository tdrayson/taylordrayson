<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Project;

class ProjectResource extends TimelineCpResource
{
    public function model(): string
    {
        return Project::class;
    }

    public function slug(): string
    {
        return 'projects';
    }

    public function label(): string
    {
        return 'Project';
    }

    public function pluralLabel(): string
    {
        return 'Projects';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['title', 'status'];
    }
}
