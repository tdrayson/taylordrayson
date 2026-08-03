<?php

namespace App\Actions\Appearances;

use App\Models\Appearance;

class DeleteAppearance
{
    public function __invoke(Appearance $appearance): void
    {
        $appearance->delete();
    }
}
