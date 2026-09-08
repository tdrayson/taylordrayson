<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Models\State;

beforeEach(function () {
    State::query()->create([
        'key' => 'now.location',
        'value' => json_encode([
            'city' => 'Whyteleafe', 'state' => 'England', 'county' => 'Surrey',
            'country_code' => 'GB', 'timezone' => 'Europe/London',
            'street' => 'Greensted Court', 'postcode' => 'CR3 0GP',
        ]),
        'observed_at' => '2026-09-08 18:12:00',
    ]);
});

it('resolves a mapped ambient field', function () {
    expect(app(DynamicTagRegistry::class)->value('ambient.location.city', [])['text'])
        ->toBe('Whyteleafe');
});

it('resolves the county that was added to the safe subset', function () {
    expect(app(DynamicTagRegistry::class)->value('ambient.location.county', [])['text'])
        ->toBe('Surrey');
});

it('does not expose a field NowState deliberately withholds', function () {
    $registry = app(DynamicTagRegistry::class);

    expect($registry->find('ambient.location.postcode'))->toBeNull()
        ->and($registry->find('ambient.location.street'))->toBeNull()
        ->and($registry->find('ambient.location.latitude'))->toBeNull();
});

it('renders the country as a code or a name', function () {
    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('ambient.location.country', ['format' => 'code'])['text'])->toBe('GB')
        ->and($registry->value('ambient.location.country', ['format' => 'name'])['text'])->toBe('United Kingdom');
});

it('renders the timezone four ways', function (string $format, string $expected) {
    expect(app(DynamicTagRegistry::class)->value('ambient.location.timezone', ['format' => $format])['text'])
        ->toBe($expected);
})->with([
    ['identifier', 'Europe/London'],
    ['abbreviation', 'BST'],
    ['offset', '+01:00'],
]);

it('derives a ring percentage against its goal', function () {
    State::query()->create([
        'key' => 'now.rings',
        'value' => json_encode(['move' => 184, 'move_goal' => 250]),
        'observed_at' => '2026-09-08 18:12:00',
    ]);

    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('ambient.rings.move.percent', [])['text'])->toBe('74%')
        ->and($registry->value('ambient.rings.move.goal', [])['text'])->toBe('250');
});
