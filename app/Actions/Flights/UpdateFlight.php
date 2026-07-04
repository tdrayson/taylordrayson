<?php

namespace App\Actions\Flights;

use App\Models\Flight;

class UpdateFlight
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Flight $flight, array $attributes): Flight
    {
        $flight->fill($attributes)->save();

        return $flight->refresh();
    }
}
