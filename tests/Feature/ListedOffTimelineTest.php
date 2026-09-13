<?php

use App\Enums\EntryStatus;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Flight;
use App\Models\Page;
use App\Models\Place;
use App\Queries\PeriodStats;
use App\Queries\PhotoStream;
use App\Stories\FlightStory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

it('keeps unlisted photos and a draft cover out of the photo stream', function () {
    Storage::fake('public');

    $listed = Place::factory()->create(['occurred_at' => now()->subDay()]);
    $listed->addMediaFromString(fakeJpeg())->usingFileName('listed.jpg')->toMediaCollection('photos');

    Place::factory()->create(['status' => EntryStatus::Unlisted])
        ->addMediaFromString(fakeJpeg())->usingFileName('unlisted.jpg')->toMediaCollection('photos');

    Article::factory()->draft()->create()
        ->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');

    $stream = app(PhotoStream::class);

    expect($stream->count())->toBe(1)
        ->and(collect($stream())->pluck('url')->all())->toBe([$listed->url()]);
});

it('counts only listed entries in period stats', function () {
    Activity::factory()->create(['occurred_at' => '2026-06-10 09:00:00']);
    Activity::factory()->create(['occurred_at' => '2026-06-11 09:00:00', 'status' => EntryStatus::Unlisted]);

    $stats = app(PeriodStats::class)(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect(collect($stats)->firstWhere('label', 'Activities')['value'])->toBe('1');
});

it('offers no archive chip for a category only an unlisted place has', function () {
    Place::factory()->create(['type' => 'Coffee Shop']);
    Place::factory()->create(['type' => 'Secret Bar', 'status' => EntryStatus::Unlisted]);

    get('/places')->assertInertia(fn (Assert $page) => $page
        ->where('chips', fn ($chips) => ! collect($chips)->pluck('label')->contains('Secret Bar')));
});

it('builds the flight story with an undated draft flight in the table', function () {
    Flight::factory()->create(['occurred_at' => '2025-06-01 09:00:00']);
    Flight::factory()->create(['occurred_at' => null, 'status' => EntryStatus::Draft]);

    expect(app(FlightStory::class)->build())->toBeArray();
});

it('tells crawlers not to index unlisted entries and private pages', function () {
    $article = Article::factory()->create(['status' => EntryStatus::Unlisted, 'occurred_at' => '2026-06-15 09:00:00']);
    Page::factory()->create(['slug' => 'locked', 'status' => EntryStatus::Private, 'password' => 'hunter2']);
    Page::factory()->create(['slug' => 'open']);

    get('/2026/06/15/'.$article->slug)->assertInertia(fn (Assert $page) => $page->where('og.noindex', true));
    get('/locked')->assertInertia(fn (Assert $page) => $page->where('og.noindex', true));
    get('/open')->assertInertia(fn (Assert $page) => $page->where('og.noindex', false));
});
