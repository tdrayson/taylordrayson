<?php

use App\Cp\ResourceRegistry;
use App\Cp\Resources\FlightResource;

it('resolves resources by slug', function () {
    $registry = app(ResourceRegistry::class);

    expect($registry->find('flights'))->toBeInstanceOf(FlightResource::class);
    expect($registry->find('nope'))->toBeNull();
});

it('registers all sixteen resources', function () {
    expect(app(ResourceRegistry::class)->all())->toHaveCount(16);
});

it('builds grouped navigation', function () {
    $nav = app(ResourceRegistry::class)->nav();

    $groups = collect($nav)->pluck('group');

    expect($groups)->toContain('Timeline');
    expect($groups)->toContain('Reference');
});

it('applies the cabin class select override on flights', function () {
    $fields = collect(app(ResourceRegistry::class)->find('flights')->fields());
    $cabin = $fields->firstWhere('key', 'cabin_class');

    expect($cabin['type'])->toBe('select');
    expect($cabin['options'])->toContain(['value' => 'business', 'label' => 'Business']);
});
