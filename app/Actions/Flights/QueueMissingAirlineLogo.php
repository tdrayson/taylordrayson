<?php

namespace App\Actions\Flights;

use App\Models\Flight;
use Illuminate\Support\Facades\Artisan;

class QueueMissingAirlineLogo
{
    /**
     * Queues the logo download for a flight's airline when its images are not on disk yet.
     */
    public function __invoke(Flight $flight): void
    {
        $airline = $flight->unsetRelation('airline')->airline;

        if ($airline?->iata_code && ($airline->icon_url === null || $airline->logo_url === null)) {
            Artisan::queue('airlines:logos', ['iata' => [$airline->iata_code]]);
        }
    }
}
