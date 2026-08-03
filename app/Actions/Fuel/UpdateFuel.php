<?php

namespace App\Actions\Fuel;

use App\Models\Fuel;

class UpdateFuel
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Fuel $fuel, array $attributes): Fuel
    {
        $fuel->fill($attributes)->save();

        return $fuel->refresh();
    }
}
