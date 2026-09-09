<?php

use App\Enums\MediaType;
use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Media;
use App\Models\Series;
use App\Presenters\CardPresenter;
use App\Queries\PhotoStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('returns real photos newest first, and the limited call is a prefix of the full one', function () {
    Storage::fake('public');

    $oldest = Checkin::factory()->create(['occurred_at' => now()->subYears(5)]);
    $oldest->addMediaFromString(fakeJpeg())->usingFileName('old.jpg')->toMediaCollection('photos');

    $newest = Activity::factory()->create(['occurred_at' => now()->subDay()]);
    $newest->addMediaFromString(fakeJpeg())->usingFileName('new.jpg')->toMediaCollection('cover');

    // Enrichment art that /photos excludes must never enter the stream.
    Media::factory()->create(['type' => MediaType::Film])
        ->addMediaFromString(fakeJpeg())->usingFileName('poster.jpg')->toMediaCollection('cover');
    Series::factory()->create()
        ->addMediaFromString(fakeJpeg())->usingFileName('series.jpg')->toMediaCollection('cover');

    $stream = app(PhotoStream::class);

    $all = $stream();
    $limited = $stream(1);

    expect($all)->toHaveCount(2)
        ->and($all[0]['url'])->toBe($newest->url())
        ->and($all[1]['url'])->toBe($oldest->url())
        // The limited call shapes fewer photos but returns the same newest-first
        // prefix, so /now is always a leading slice of /photos.
        ->and($limited)->toHaveCount(1)
        ->and($limited[0]['url'])->toBe($all[0]['url']);
});

it('pages the stream without shaping or dropping photos at a group boundary', function () {
    Storage::fake('public');

    // Three entries owning two photos each, so a page size of 3 splits the
    // middle group: the boundary an offset is most likely to get wrong.
    foreach (range(1, 3) as $day) {
        $checkin = Checkin::factory()->create(['occurred_at' => now()->subDays($day)]);
        $checkin->addMediaFromString(fakeJpeg())->usingFileName("a{$day}.jpg")->toMediaCollection('photos');
        $checkin->addMediaFromString(fakeJpeg())->usingFileName("b{$day}.jpg")->toMediaCollection('photos');
    }

    $stream = app(PhotoStream::class);
    $all = $stream();

    expect($stream->count())->toBe(6)
        ->and($all)->toHaveCount(6)
        // Consecutive pages reassemble the full stream in order, with nothing
        // repeated across the group the boundary cuts through.
        ->and([...$stream(3, 0), ...$stream(3, 3)])->toBe($all)
        // Every page size reassembles exactly, including sizes that split a
        // group and sizes larger than the stream.
        ->and([...$stream(1, 0), ...$stream(1, 1), ...$stream(1, 2), ...$stream(1, 3), ...$stream(1, 4), ...$stream(1, 5)])->toBe($all)
        ->and($stream(100, 0))->toBe($all)
        // Asking past the end is empty rather than a wrapped page.
        ->and($stream(3, 6))->toBe([])
        ->and($stream(3, 99))->toBe([]);
});

it('captions a photo with its entry title, accent and permalink', function () {
    Storage::fake('public');

    $checkin = Checkin::factory()->create([
        'occurred_at' => now(),
        'venue_name' => 'Cineworld',
        'event_name' => null,
    ]);
    $checkin->addMediaFromString(fakeJpeg())->usingFileName('me.jpg')->toMediaCollection('photos');

    // The caption is built without the card presenter now, so it has to keep
    // matching the card a visitor sees on the entry itself.
    expect(app(PhotoStream::class)()[0])
        ->toMatchArray([
            'caption' => CardPresenter::for($checkin->fresh())->title,
            'accent' => 'checkin',
            'url' => $checkin->url(),
        ]);
});
