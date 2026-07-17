<?php

use App\Models\Activity;

it('stores and casts the stream columns as arrays', function () {
    $activity = Activity::factory()->create([
        'altitude' => [['time' => '2024-01-01 00:00:00', 'value' => 12.5]],
        'speed' => [['time' => '2024-01-01 00:00:00', 'value' => 3.2]],
        'track' => [['time' => '2024-01-01 00:00:00', 'lat' => 51.5, 'lng' => -0.1]],
    ]);

    $fresh = $activity->fresh();

    expect($fresh->altitude)->toBeArray()->and($fresh->altitude[0]['value'])->toBe(12.5);
    expect($fresh->speed[0]['value'])->toBe(3.2);
    expect($fresh->track[0]['lat'])->toBe(51.5);
});
