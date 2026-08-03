<?php

namespace App\Presenters\Entries;

use App\Models\Fuel;
use App\Queries\FuelEconomy;

/**
 * The fuel-specific additions to an entry page payload: the tank's range and
 * economy, plus the vehicle it was put in.
 *
 * Lives here rather than on EntryController so a single type's payload shaping
 * does not put a permanent dependency on a controller serving all thirteen.
 */
final class FuelEntry
{
    /**
     * @return array{miles_this_tank: int|null, mpg: float|null, vehicle: string|null}
     */
    public function present(Fuel $fuel): array
    {
        $economy = (new FuelEconomy)($fuel);

        return [
            'miles_this_tank' => $economy['miles'],
            'mpg' => $economy['mpg'],
            'vehicle' => $economy['vehicle'],
        ];
    }
}
