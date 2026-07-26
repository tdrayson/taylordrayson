<?php

use App\Enums\MediaType;
use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Media;
use App\Models\Series;
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
