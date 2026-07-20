<?php

use App\Enums\ActivityType;

it('has exactly the eleven known stored values', function () {
    expect(array_column(ActivityType::cases(), 'value'))
        ->toBe([
            'run',
            'walk',
            'ride',
            'e-bike-ride',
            'swim',
            'weight-training',
            'workout',
            'yoga',
            'ice-skate',
            'padel',
            'table-tennis',
        ]);
});

it('labels each case for display', function () {
    expect(ActivityType::Run->label())->toBe('Run')
        ->and(ActivityType::EbikeRide->label())->toBe('E-bike ride')
        ->and(ActivityType::WeightTraining->label())->toBe('Weight training');
});

it('resolves from the stored backed value', function () {
    expect(ActivityType::from('e-bike-ride'))->toBe(ActivityType::EbikeRide);
});

it('does not match an unknown open-set type, since the column is not cast', function () {
    expect(ActivityType::tryFrom('kayak'))->toBeNull();
});
