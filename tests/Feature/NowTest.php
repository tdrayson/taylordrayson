<?php

use App\Models\Activity;
use App\Models\Podcast;
use App\Models\Sleep;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

it('renders the now page via Inertia', function () {
    get('/now')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Now'));
});

it('passes the latest podcast episode to the widget', function () {
    Podcast::factory()->create(['occurred_at' => now()->subDay()]);

    get('/now')->assertInertia(fn ($page) => $page
        ->has('episode.season')
        ->has('episode.media.title')
    );
});

it('passes recent sleep nights and last-night stage hours', function () {
    Sleep::factory()->create([
        'occurred_at' => now()->subDay(),
        'duration' => 28800, // 8h
        'deep' => 3600,
        'core' => 18000,
        'rem' => 5400,
        'awake' => 1800,
    ]);

    get('/now')->assertInertia(fn ($page) => $page
        ->has('sleep.nights', 1)
        ->where('sleep.nights.0', fn ($hours) => (float) $hours === 8.0)
        ->where('sleep.stageHours.deep', fn ($hours) => (float) $hours === 1.0)
        ->where('sleep.stageHours.core', fn ($hours) => (float) $hours === 5.0)
    );
});

it('counts timeline entries from the trailing 30 days', function () {
    // Each timelineable model spawns a timeline entry dated to occurred_at.
    Sleep::factory()->create(['occurred_at' => now()->subDay()]);
    Podcast::factory()->create(['occurred_at' => now()->subDays(2)]);

    get('/now')->assertInertia(fn ($page) => $page
        ->has('entryCounts', 30)
        ->where('entryCounts', fn ($counts) => collect($counts)->sum() >= 2)
    );
});

it('passes recent real photos to the deck', function () {
    Storage::fake('public');

    $activity = Activity::factory()->create(['occurred_at' => now()->subDay()]);
    $activity->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');

    get('/now')->assertInertia(fn ($page) => $page
        ->has('photos', 1)
        ->has('photos.0.src')
    );
});
