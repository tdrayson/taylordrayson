<?php

use App\Models\Flight;
use StatamicRadPack\Runway\Runway;

it('exposes flights as a runway resource without touching the model', function () {
    $resource = Runway::findResource('flight');
    expect($resource->model())->toBeInstanceOf(Flight::class);
    // The model still reads from its own table.
    expect(Flight::query()->count())->toBe(DB::table('flights')->count());
});
