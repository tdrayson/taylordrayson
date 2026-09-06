<?php

use App\Enums\MediaType;
use App\Enums\ReviewKind;
use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Media;
use App\Models\Series;
use App\Models\Subject;
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

it('lists only photographs with neither a tag nor a review', function () {
    Storage::fake('public');

    $tagged = Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('a.jpg')->toMediaCollection('photos');
    $tagged->subjects()->attach(Subject::factory()->person()->create(), ['role' => 'subject', 'x' => 1, 'y' => 1]);

    $reviewed = Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('b.jpg')->toMediaCollection('photos');
    $reviewed->setCustomProperty(ReviewKind::Subjects->property(), now()->toIso8601String())->save();

    Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('c.jpg')->toMediaCollection('photos');

    expect(app(PhotoStream::class)(null, 'needs-tagging'))->toHaveCount(1);
});

it('lists only photographs with no alt text', function () {
    Storage::fake('public');

    $described = Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('a.jpg')->toMediaCollection('photos');
    $described->setCustomProperty('alt', 'Something')->save();
    Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('b.jpg')->toMediaCollection('photos');

    expect(app(PhotoStream::class)(null, 'needs-alt'))->toHaveCount(1);
});

it('leaves the unfiltered stream alone', function () {
    Storage::fake('public');

    Activity::factory()->create()->addMediaFromString(fakeJpeg())->usingFileName('a.jpg')->toMediaCollection('photos');

    expect(app(PhotoStream::class)())->toHaveCount(1);
});
