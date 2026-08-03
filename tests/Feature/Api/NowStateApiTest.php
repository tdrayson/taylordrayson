<?php

use App\Support\StateStore;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

/**
 * The whole point of one endpoint serving two shortcuts: the plug-in shortcut
 * sends only the battery, and everything the scheduled shortcut wrote survives.
 */
it('leaves other groups alone when a shortcut sends only the battery', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'battery' => ['percent' => 72, 'charging' => false, 'low_power' => true],
        'weather' => ['condition' => 'partly-cloudy', 'temp' => 21],
    ])->assertOk();

    $this->withToken('test-token')->postJson('/api/v1/now', [
        'battery' => ['percent' => 80, 'charging' => true],
    ])
        ->assertOk()
        ->assertJsonPath('data.written', ['battery']);

    $state = app(StateStore::class);

    // The percent and charging flag moved; low_power was not mentioned by the
    // second send and kept its value, as did the untouched weather group.
    expect($state->get('now.battery'))->toEqual(['percent' => 80, 'charging' => true, 'low_power' => true])
        ->and($state->get('now.weather'))->toEqual(['condition' => 'partly-cloudy', 'temp' => 21]);
});

it('records when the phone observed the reading, not when it arrived', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'observed_at' => '2026-08-03T09:15:00+01:00',
        'battery' => ['percent' => 55],
    ])->assertOk();

    expect(app(StateStore::class)->entry('now.battery')['observedAt'])->toStartWith('2026-08-03T09:15:00');
});

it('rejects unknown groups and fields by name', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'battery' => ['percent' => 50, 'volts' => 3.7],
        'mood' => ['value' => 'good'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['battery.volts', 'mood']);
});

it('rejects impossible readings', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'battery' => ['percent' => 150],
        'location' => ['latitude' => 700],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['battery.percent', 'location.latitude']);
});

it('rejects a send carrying no groups at all', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', ['observed_at' => now()->toIso8601String()])
        ->assertStatus(422);
});

it('reads Shortcuts string booleans as booleans', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'battery' => ['percent' => 40, 'charging' => 'true', 'low_power' => 'false'],
    ])->assertOk();

    expect(app(StateStore::class)->get('now.battery'))
        ->toEqual(['percent' => 40, 'charging' => true, 'low_power' => false]);
});

it('requires the api token', function () {
    $this->postJson('/api/v1/now', ['battery' => ['percent' => 50]])->assertUnauthorized();
});

it('describes what it accepts, so a shortcut can be checked from the phone', function () {
    $this->withToken('test-token')->getJson('/api/v1/now')
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.accepts.battery', ['percent', 'charging', 'low_power', 'device']);
});

it('shares the last sent readings with every page, so the status bar has them too', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'battery' => ['percent' => 41, 'charging' => true, 'device' => 'iPhone 16 Pro'],
        'location' => ['city' => 'Whyteleafe, UK', 'latitude' => 51.31, 'longitude' => -0.06, 'timezone' => 'Europe/London'],
    ])->assertOk();

    $this->get('/now')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Now')
        ->where('ambient.battery.percent', 41)
        ->where('ambient.battery.charging', true)
        ->where('ambient.battery.device', 'iPhone 16 Pro')
        ->where('ambient.location.city', 'Whyteleafe, UK')
        // Never sent, so the widget keeps its own placeholder rather than blanking.
        ->where('ambient.weather', null)
    );
});

it('takes weather the way Shortcuts hands it over', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        // Temperatures arrive with their unit attached once they land in a
        // Shortcuts dictionary, and the condition is human text.
        'weather' => ['condition' => 'Partly Cloudy', 'temp' => '21°C', 'high' => '24°C', 'low' => '-2°C'],
    ])->assertOk();

    expect(app(StateStore::class)->get('now.weather'))
        ->toEqual(['condition' => 'partly-cloudy', 'temp' => 21.0, 'high' => 24.0, 'low' => -2.0]);
});

it('stores coordinates exactly but only ever renders them coarsened', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'location' => [
            'city' => 'Whyteleafe',
            'state' => 'England',
            'country_code' => 'gb',
            'latitude' => 51.3134567,
            'longitude' => -0.0612345,
        ],
    ])->assertOk();

    // Stored precisely, so a private project can use the real position.
    expect(app(StateStore::class)->get('now.location'))
        ->toEqual([
            'city' => 'Whyteleafe',
            'state' => 'England',
            'country_code' => 'GB',
            'latitude' => 51.3134567,
            'longitude' => -0.0612345,
        ]);

    // Coarsened on the only path to a public page, and the country name comes
    // from the code rather than being sent as a second value.
    $this->get('/now')->assertOk()->assertInertia(fn ($page) => $page
        ->where('ambient.location.latitude', 51.31)
        ->where('ambient.location.longitude', -0.06)
        ->where('ambient.location.country', 'United Kingdom')
    );
});

it('stores the full address but never renders it', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'location' => [
            'city' => 'Whyteleafe',
            'street' => 'Godstone Road',
            'postcode' => 'CR3 0AA',
            'name' => 'David Lloyd Purley Way',
            'latitude' => 51.3134567,
        ],
    ])->assertOk();

    expect(app(StateStore::class)->get('now.location'))
        ->toMatchArray(['street' => 'Godstone Road', 'postcode' => 'CR3 0AA', 'name' => 'David Lloyd Purley Way']);

    // The public payload is built from an allowlist, so the address is absent
    // by construction rather than by remembering to strip it.
    $this->get('/now')->assertOk()->assertInertia(fn ($page) => $page
        ->where('ambient.location.city', 'Whyteleafe')
        ->where('ambient.location.latitude', 51.31)
        ->missing('ambient.location.street')
        ->missing('ambient.location.postcode')
        ->missing('ambient.location.name')
    );
});

it('takes humidity and wind with their units attached', function () {
    $this->withToken('test-token')->postJson('/api/v1/now', [
        'weather' => ['temp' => '21°C', 'humidity' => '62%', 'wind' => '8 mph'],
    ])->assertOk();

    expect(app(StateStore::class)->get('now.weather'))
        ->toEqual(['temp' => 21.0, 'humidity' => 62.0, 'wind' => 8.0]);
});
