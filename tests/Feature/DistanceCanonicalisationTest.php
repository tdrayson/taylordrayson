<?php

use App\Models\Activity;
use App\Models\Flight;
use App\Support\Distance;
use Illuminate\Support\Facades\Schema;

it('stores the activity distance column as an integer type', function () {
    expect(Schema::getColumnType('activities', 'distance'))->toBe('integer');
});

it('stores activity and flight distance in integer metres', function () {
    $activity = Activity::factory()->create(['distance' => 5230]);
    $flight = Flight::factory()->create(['distance' => 1245632]);

    expect($activity->fresh()->distance)->toBe(5230)
        ->and($flight->fresh()->distance)->toBe(1245632);
});

it('converts metres for display', function () {
    expect(Distance::km(5230))->toBe(5.2)
        ->and(Distance::miles(1245632))->toBe(774)
        ->and(Distance::km(null))->toBeNull()
        ->and(Distance::miles(null))->toBeNull();
});
