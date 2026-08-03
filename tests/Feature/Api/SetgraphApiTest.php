<?php

use App\Models\Activity;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

$share = <<<'TEXT'
Lat Pulldown • 12 rep: 32, 36, 36 kg
Trx push up • 3 sets: 12 rep

Other • 38 min

Tracked on Setgraph
TEXT;

it('creates a placeholder activity when Strava has not synced the session yet', function () use ($share) {
    $this->withToken('test-token')->postJson('/api/v1/setgraph', [
        'text' => $share,
        'occurred_at' => '2026-07-27 19:49:17',
        'timezone' => 'Europe/London',
    ])
        ->assertCreated()
        ->assertJsonPath('data.sets', 6)
        ->assertJsonPath('data.exercises', 2)
        ->assertJsonPath('data.created', true);

    $activity = Activity::sole();

    expect($activity->source)->toBe('setgraph')
        ->and($activity->source_id)->toBeNull()
        ->and($activity->type)->toBe('weight-training')
        ->and($activity->duration)->toBe(2280)
        ->and($activity->meta['sets'])->toHaveCount(6)
        // Bodyweight survives the JSON round-trip as a zero weight, which the
        // entry view renders as "Bodyweight".
        ->and($activity->meta['sets'][3])->toEqual(['exercise' => 'Trx push up', 'reps' => 12, 'weight_kg' => 0]);
});

it('merges the sets onto the Strava activity when that synced first', function () use ($share) {
    $existing = Activity::factory()->create([
        'occurred_at' => '2026-07-27 19:49:17',
        'type' => 'weight-training',
        'name' => 'Evening Weight Training',
        'duration' => 2311,
        'source' => 'strava',
        'source_id' => '19532725079',
        'meta' => ['sport_type' => 'WeightTraining'],
    ]);

    // Shared 20 minutes after the activity started, still the same session.
    $this->withToken('test-token')->postJson('/api/v1/setgraph', [
        'text' => $share,
        'occurred_at' => '2026-07-27 20:09:00',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $existing->id)
        ->assertJsonPath('data.created', false);

    $existing->refresh();

    expect(Activity::count())->toBe(1)
        ->and($existing->source_id)->toBe('19532725079')
        // Strava's real moving time survives Setgraph's rounded 38 min.
        ->and($existing->duration)->toBe(2311)
        ->and($existing->meta['sport_type'])->toBe('WeightTraining')
        ->and($existing->meta['sets'])->toHaveCount(6);
});

it('does not claim a cardio activity that happens to sit in the same window', function () use ($share) {
    Activity::factory()->create([
        'occurred_at' => '2026-07-27 19:50:00',
        'type' => 'walk',
        'source' => 'strava',
        'source_id' => '111',
    ]);

    $this->withToken('test-token')->postJson('/api/v1/setgraph', [
        'text' => $share,
        'occurred_at' => '2026-07-27 19:49:17',
    ])->assertCreated();

    expect(Activity::count())->toBe(2);
});

it('reads an ISO 8601 share time as wall clock, matching the session it belongs to', function () use ($share) {
    $existing = Activity::factory()->create([
        'occurred_at' => '2026-07-27 19:49:17',
        'type' => 'weight-training',
        'source' => 'strava',
        'source_id' => '19532725079',
    ]);

    $this->withToken('test-token')->postJson('/api/v1/setgraph', [
        'text' => $share,
        'occurred_at' => '2026-07-27T20:10:00+01:00',
        'timezone' => 'Europe/London',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $existing->id);

    expect(Activity::count())->toBe(1);
});

it('matches on the wall clock even when the offset says otherwise', function () use ($share) {
    // Shared at 20:10 local while abroad. As an instant that is nine hours from
    // a session logged at 19:49, but as wall-clock time it is the same evening.
    $existing = Activity::factory()->create([
        'occurred_at' => '2026-07-27 19:49:17',
        'type' => 'weight-training',
        'source' => 'strava',
        'source_id' => '19532725079',
    ]);

    $this->withToken('test-token')->postJson('/api/v1/setgraph', [
        'text' => $share,
        'occurred_at' => '2026-07-27T20:10:00-04:00',
        'timezone' => 'America/New_York',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $existing->id);

    expect(Activity::count())->toBe(1);
});

it('stores the shared wall-clock time and zone on an activity it creates', function () use ($share) {
    $this->withToken('test-token')->postJson('/api/v1/setgraph', [
        'text' => $share,
        'occurred_at' => '2026-07-27T20:10:00-04:00',
        'timezone' => 'America/New_York',
    ])->assertCreated();

    $activity = Activity::sole();

    expect($activity->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-07-27 20:10:00')
        ->and($activity->timezone)->toBe('America/New_York');
});

it('requires the share text', function () {
    $this->withToken('test-token')->postJson('/api/v1/setgraph', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['text']);
});

it('rejects an unauthenticated post', function () use ($share) {
    $this->postJson('/api/v1/setgraph', ['text' => $share])->assertUnauthorized();

    expect(Activity::count())->toBe(0);
});
