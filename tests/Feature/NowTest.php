<?php

use App\Models\Activity;
use App\Models\Film;
use App\Models\Place;
use App\Models\Sleep;
use App\Models\ThisWeekWith;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

it('renders the now page via Inertia', function () {
    get('/now')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Now'));
});

it('passes the latest podcast episode to the widget', function () {
    ThisWeekWith::factory()->create(['occurred_at' => now()->subDay()]);

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

    // Seven dated nights whether or not each has a record, and the split for
    // whichever one the headline shows: here last night, today having none.
    get('/now')->assertInertia(fn ($page) => $page
        ->has('sleep.nights', 7)
        ->where('sleep.nights.5.hours', fn ($hours) => (float) $hours === 8.0)
        ->where('sleep.nights.6.hours', null)
        ->where('sleep.lastNight.date', now()->subDay()->toDateString())
        ->where('sleep.lastNight.stageHours.deep', fn ($hours) => (float) $hours === 1.0)
        ->where('sleep.lastNight.stageHours.core', fn ($hours) => (float) $hours === 5.0)
    );
});

it('counts timeline entries across four Monday-first weeks', function () {
    // A Wednesday, so the current week has both past and future days.
    $this->travelTo('2026-09-16 12:00:00');

    // Each Timelineable model spawns a timeline entry dated to occurred_at.
    Sleep::factory()->create(['occurred_at' => '2026-09-15 07:00:00']);
    ThisWeekWith::factory()->create(['occurred_at' => '2026-08-24 18:00:00']);

    get('/now')->assertInertia(fn ($page) => $page
        ->has('entryDays', 28)
        ->where('entryDays.0', ['date' => '2026-08-24', 'count' => 1])
        ->where('entryDays.22.count', 1)
        ->where('entryDays.23', ['date' => '2026-09-16', 'count' => 0])
        ->where('entryDays.24', ['date' => '2026-09-17', 'count' => null])
        ->where('entryDays.27.date', '2026-09-20')
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

it('orders the deck newest first and excludes posters, matching /photos', function () {
    Storage::fake('public');

    // An older personal photo and a newer one, on different entry types.
    $place = Place::factory()->create(['occurred_at' => now()->subYears(5)]);
    $place->addMediaFromString(fakeJpeg())->usingFileName('old.jpg')->toMediaCollection('photos');

    $activity = Activity::factory()->create(['occurred_at' => now()->subDay()]);
    $activity->addMediaFromString(fakeJpeg())->usingFileName('new.jpg')->toMediaCollection('cover');

    // A film poster (cover) is enrichment art, not a photo taken.
    Film::factory()->create()
        ->addMediaFromString(fakeJpeg())->usingFileName('poster.jpg')->toMediaCollection('cover');

    get('/now')->assertInertia(fn (Assert $page) => $page
        // The poster is gone; only the two real photos remain.
        ->has('photos', 2)
        // Newest entry (yesterday's activity) leads, not the recently-imported
        // but five-year-old check-in.
        ->where('photos.0.url', $activity->url())
        ->where('photos.1.url', $place->url())
    );
});
