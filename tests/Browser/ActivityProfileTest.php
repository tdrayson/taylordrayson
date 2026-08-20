<?php

use App\Models\Activity;

it('renders the profile charts on an activity with streams', function () {
    $points = fn (callable $v) => collect(range(0, 20))->map(fn ($i) => ['time' => now()->addSeconds($i)->format('Y-m-d H:i:s')] + $v($i))->all();

    $activity = Activity::factory()->create([
        'type' => 'run',
        'occurred_at' => now(),
        'duration' => 1200,
        'meta' => ['polyline' => 'ki~mHvfyLPKLA'],
        'heart_rate' => $points(fn ($i) => ['bpm' => 120 + $i]),
        'altitude' => $points(fn ($i) => ['value' => 10 + $i]),
        'speed' => $points(fn ($i) => ['value' => 2 + $i / 10]),
    ]);

    $page = visit($activity->url());

    // Rendered DOM: the profile section renders three SVG charts (one per series).
    $page->assertPresent('[data-testid="activity-profile"] svg');
});

it('renders the profile charts alongside the exercise list on a gym session', function () {
    $activity = Activity::factory()->create([
        'type' => 'weight-training',
        'occurred_at' => now(),
        'duration' => 1200,
        'heart_rate' => collect(range(0, 20))
            ->map(fn ($i) => ['time' => now()->addSeconds($i)->format('Y-m-d H:i:s'), 'bpm' => 120 + $i])
            ->all(),
        'meta' => ['sets' => [['exercise' => 'Barbell Skullcrusher', 'reps' => 12, 'weight_kg' => 16]]],
    ]);

    $page = visit($activity->url());

    $page->assertPresent('[data-testid="activity-profile"] svg')
        ->assertSee('Barbell Skullcrusher');
});
