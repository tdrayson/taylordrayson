<?php

use App\Models\Activity;

use function Pest\Laravel\get;

it('renders the activities stats dashboard', function () {
    get('/stats/activities')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Stats')
            ->where('type', 'activities')
            ->has('accent')
            ->has('range.from')
            ->has('range.to')
            ->has('range.label')
            ->has('compare.mode')
            ->has('metrics')
            ->has('averageLabel')
            ->has('perWeek')
            ->has('byType')
            ->has('busiest')
            ->has('trend.buckets')
            ->has('records')
            ->has('routes'));
});

it('filters metrics to the requested range', function () {
    Activity::factory()->count(3)->create(['type' => 'walk', 'occurred_at' => '2024-03-10 09:00:00']);
    Activity::factory()->create(['type' => 'walk', 'occurred_at' => '2024-06-01 09:00:00']); // outside the range

    get('/stats/activities?from=2024-03-01&to=2024-03-31')
        ->assertInertia(fn ($page) => $page
            ->where('metrics.0.label', 'Sessions')
            ->where('metrics.0.value', '3')
            ->where('range.from', '2024-03-01')
            ->where('range.to', '2024-03-31'));
});

it('computes a delta against the previous period', function () {
    Activity::factory()->count(4)->create(['type' => 'walk', 'occurred_at' => '2024-03-15 09:00:00']);
    Activity::factory()->count(2)->create(['type' => 'walk', 'occurred_at' => '2024-02-15 09:00:00']);

    get('/stats/activities?from=2024-03-01&to=2024-03-31&compare=previous-period')
        ->assertInertia(fn ($page) => $page->where('metrics.0.delta', 100));
});

it('drops deltas when comparison is off', function () {
    Activity::factory()->count(4)->create(['type' => 'walk', 'occurred_at' => '2024-03-15 09:00:00']);

    get('/stats/activities?from=2024-03-01&to=2024-03-31&compare=none')
        ->assertInertia(fn ($page) => $page
            ->where('compare.mode', 'none')
            ->where('metrics.0.delta', null));
});

it('redirects /{type}/stats to the canonical /stats/{type}', function () {
    get('/activities/stats')
        ->assertRedirect('/stats/activities')
        ->assertStatus(301);
});

it('404s for a type without a stats dashboard', function () {
    get('/stats/sleep')->assertNotFound();
});

it('404s for an unknown type', function () {
    get('/stats/nonsense')->assertNotFound();
});
