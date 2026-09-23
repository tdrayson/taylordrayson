<?php

namespace App\Actions\Flights;

use App\Models\Flight;

class UpdateFlight
{
    public function __construct(private QueueMissingAirlineLogo $queueAirlineLogo) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Flight $flight, array $attributes): Flight
    {
        $flight->fill($attributes)->save();

        ($this->queueAirlineLogo)($flight);

        return $flight->refresh();
    }
}
