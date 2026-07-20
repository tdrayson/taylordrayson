<?php

use App\Enums\Source;

it('has exactly the eleven known stored values', function () {
    expect(array_column(Source::cases(), 'value'))
        ->toBe([
            'strava',
            'setgraph',
            'apple_watch',
            'clock',
            'health',
            'iphone',
            'oura',
            'loseit',
            'rovi',
            'swarm',
            'trakt',
        ]);
});

it('resolves from the stored backed value', function () {
    expect(Source::from('apple_watch'))->toBe(Source::AppleWatch);
});

it('does not match an unknown legacy source, since the column is not cast', function () {
    expect(Source::tryFrom('unknown'))->toBeNull();
});
