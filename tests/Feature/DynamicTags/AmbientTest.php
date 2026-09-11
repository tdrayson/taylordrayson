<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Models\State;
use Illuminate\Support\Facades\DB;

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
    ['long', 'British Summer Time'],
]);

it('reads the ambient state once per request no matter how many tags are resolved', function () {
    DB::enableQueryLog();

    $registry = app(DynamicTagRegistry::class);
    $registry->value('ambient.location.city', []);
    $registry->value('ambient.location.county', []);
    $registry->value('ambient.weather.temp', []);

    $stateQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'states'));

    expect($stateQueries)->toHaveCount(1);
});

it('renders a boolean field as a word, not the empty string PHP casts false to', function (bool $charging, string $expected) {
    State::query()->create([
        'key' => 'now.battery',
        'value' => json_encode(['percent' => 50, 'charging' => $charging, 'low_power' => false]),
        'observed_at' => '2026-09-08 18:12:00',
    ]);

    expect(app(DynamicTagRegistry::class)->value('ambient.battery.charging', [])['text'])->toBe($expected);
})->with([
    'true' => [true, 'Yes'],
    'false' => [false, 'No'],
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

it('leaves a ring value and its goal bare, they are points not a physical unit', function () {
    State::query()->create([
        'key' => 'now.rings',
        'value' => json_encode(['move' => 118, 'move_goal' => 250, 'steps' => 4213]),
        'observed_at' => '2026-09-08 18:12:00',
    ]);

    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('ambient.rings.move', [])['text'])->toBe('118')
        ->and($registry->value('ambient.rings.move.goal', [])['text'])->toBe('250')
        ->and($registry->value('ambient.rings.steps', [])['text'])->toBe('4,213');
});

it('groups a ring value, its goal and its percent under one subgroup', function () {
    $registry = app(DynamicTagRegistry::class);

    expect($registry->find('ambient.rings.move')->subgroup())->toBe('Move')
        ->and($registry->find('ambient.rings.move.goal')->subgroup())->toBe('Move')
        ->and($registry->find('ambient.rings.move.percent')->subgroup())->toBe('Move')
        ->and($registry->find('ambient.rings.steps')->subgroup())->toBeNull()
        ->and($registry->find('ambient.rings.updated')->subgroup())->toBeNull()
        ->and($registry->find('ambient.battery.percent')->subgroup())->toBeNull();
});

it('suffixes a reading that is meaningless without its unit', function () {
    State::query()->create([
        'key' => 'now.battery',
        'value' => json_encode(['percent' => 72, 'charging' => false, 'low_power' => false]),
        'observed_at' => '2026-09-08 18:12:00',
    ]);
    State::query()->create([
        'key' => 'now.weather',
        'value' => json_encode(['condition' => 'clear', 'temp' => 16, 'humidity' => 88, 'wind' => 7]),
        'observed_at' => '2026-09-08 18:12:00',
    ]);

    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('ambient.battery.percent', [])['text'])->toBe('72%')
        ->and($registry->value('ambient.weather.temp', [])['text'])->toBe('16°C')
        ->and($registry->value('ambient.weather.humidity', [])['text'])->toBe('88%')
        ->and($registry->value('ambient.weather.wind', [])['text'])->toBe('7 mph');
});
