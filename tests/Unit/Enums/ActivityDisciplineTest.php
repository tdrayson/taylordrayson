<?php

use App\Enums\ActivityDiscipline;

it('has exactly the five behaviour-bearing values', function () {
    expect(array_column(ActivityDiscipline::cases(), 'value'))
        ->toBe([
            'run',
            'walk',
            'ride',
            'e-bike-ride',
            'swim',
        ]);
});

it('labels each case for display', function () {
    expect(ActivityDiscipline::Run->label())->toBe('Run')
        ->and(ActivityDiscipline::EbikeRide->label())->toBe('E-bike ride')
        ->and(ActivityDiscipline::Swim->label())->toBe('Swim');
});

it('resolves from the stored backed value', function () {
    expect(ActivityDiscipline::from('e-bike-ride'))->toBe(ActivityDiscipline::EbikeRide);
});

it('does not match an unknown open-set type, since the column is not cast', function () {
    expect(ActivityDiscipline::tryFrom('yoga'))->toBeNull();
});
