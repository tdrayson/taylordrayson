<?php

namespace App\Actions\Appearances;

use App\Models\Appearance;

class UpdateAppearance
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Appearance $appearance, array $attributes): Appearance
    {
        $appearance->fill($attributes)->save();

        return $appearance->refresh();
    }
}
