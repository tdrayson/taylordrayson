<?php

namespace App\Actions\Flights;

use App\Models\Flight;

class DeleteFlight
{
    public function __invoke(Flight $flight): void
    {
        $flight->delete();
    }
}
