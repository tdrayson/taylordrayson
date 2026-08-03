<?php

namespace App\Actions\Projects;

use App\Models\Project;

class DeleteProject
{
    public function __invoke(Project $project): void
    {
        $project->delete();
    }
}
