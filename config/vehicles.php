<?php

/*
|--------------------------------------------------------------------------
| Vehicles
|--------------------------------------------------------------------------
|
| The car a fuel fill-up belongs to (issue #27). Kept in config rather than a
| database table: there is effectively one car, and its details are static
| reference data, not user-editable records or a growing set. Each `fuel` row
| stores a `vehicle_id` (the number plate, lower-cased) that keys into this
| map. `Fuel::getVehicleAttribute()` resolves it, `TypeRegistry` derives the
| vehicle filter label from `model`, and `FuelStory` reads `make`/`model`.
| Adding a second car is just another entry here (mark the previous one
| `'active' => false`). Only move to a `vehicles` table if a car ever needs
| its own editable metadata or relations beyond fuel.
|
*/

return [
    'hn14wxp' => [
        'make' => 'Toyota',
        'model' => 'Aygo',
        'year' => 2014,
        'fuel_type' => 'petrol',
        'active' => true,
    ],
];
