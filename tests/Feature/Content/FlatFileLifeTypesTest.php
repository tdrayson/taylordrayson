<?php

use App\Content\EntryFileRepository;
use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Media;
use App\Models\Podcast;
use App\Models\Sleep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->contentPath = storage_path('framework/testing/content-life-'.uniqid('', true));
    File::ensureDirectoryExists($this->contentPath);
    config(['content.path' => $this->contentPath]);
});

afterEach(function () {
    if (isset($this->contentPath) && File::isDirectory($this->contentPath)) {
        File::deleteDirectory($this->contentPath);
    }
});

it('writes an activity markdown file', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-03-01 07:00:00',
        'type' => 'Run',
        'name' => 'Morning Run',
        'description' => 'Easy pace',
        'distance' => 5000,
    ]);

    $path = $this->contentPath.'/2026/03/01/morning-run.md';

    expect(File::exists($path))->toBeTrue();

    $parsed = app(EntryFileRepository::class)->parse(File::get($path));

    expect($parsed['frontmatter']['type'])->toBe('activity')
        ->and($parsed['frontmatter']['kind'])->toBe('Run')
        ->and($parsed['frontmatter']['id'])->toBe($activity->ulid)
        ->and($parsed['body'])->toContain('Easy pace');
});

it('writes a sleep file as sleep.md', function () {
    Sleep::factory()->create([
        'occurred_at' => '2026-03-02 00:00:00',
        'bedtime' => '2026-03-01 23:00:00',
        'wake_time' => '2026-03-02 07:00:00',
        'duration' => 28800,
    ]);

    expect(File::exists($this->contentPath.'/2026/03/02/sleep.md'))->toBeTrue();
});

it('writes one calories file per day with all food items', function () {
    Calorie::factory()->create([
        'occurred_at' => '2026-03-03 08:00:00',
        'name' => 'Oats',
        'meal' => 'breakfast',
        'calories' => 300,
    ]);

    Calorie::factory()->create([
        'occurred_at' => '2026-03-03 12:30:00',
        'name' => 'Sandwich',
        'meal' => 'lunch',
        'calories' => 450,
    ]);

    $path = $this->contentPath.'/2026/03/03/calories.md';

    expect(File::exists($path))->toBeTrue();

    $parsed = app(EntryFileRepository::class)->parse(File::get($path));

    expect($parsed['frontmatter']['type'])->toBe('calorie')
        ->and($parsed['frontmatter']['slug'])->toBe('calories')
        ->and($parsed['frontmatter']['items'])->toHaveCount(2)
        ->and(collect($parsed['frontmatter']['items'])->pluck('name')->all())
        ->toContain('Oats', 'Sandwich');
});

it('removes the calories day file when the last item is deleted', function () {
    $calorie = Calorie::factory()->create([
        'occurred_at' => '2026-03-04 09:00:00',
        'name' => 'Coffee',
        'calories' => 5,
    ]);

    $path = $this->contentPath.'/2026/03/04/calories.md';
    expect(File::exists($path))->toBeTrue();

    $calorie->delete();

    expect(File::exists($path))->toBeFalse();
});

it('writes podcast and media files', function () {
    Podcast::factory()->create([
        'occurred_at' => '2026-03-05 10:00:00',
        'season_number' => 1,
        'episode_number' => 2,
        'topic' => 'Flat files',
    ]);

    Media::factory()->create([
        'occurred_at' => '2026-03-05 20:00:00',
        'type' => 'film',
        'title' => 'Arrival',
        'rating' => 9,
    ]);

    expect(File::exists($this->contentPath.'/2026/03/05/tww-s1-e2.md'))->toBeTrue()
        ->and(File::exists($this->contentPath.'/2026/03/05/arrival.md'))->toBeTrue();
});

it('reindexes a calorie day file via content:sync', function () {
    $this->contentDb = storage_path('framework/testing/content-cal-'.uniqid('', true).'.sqlite');
    config(['database.connections.content.database' => $this->contentDb]);
    DB::purge('content');

    $dayId = '01JTESTCALORIEDAY000000000';
    $itemId = '01JTESTCALORIEITEM00000000';
    $path = $this->contentPath.'/2026/03/06/calories.md';
    File::ensureDirectoryExists(dirname($path));
    File::put($path, <<<MD
---
id: {$dayId}
type: calorie
occurred_at: 2026-03-06T12:00:00+00:00
slug: calories
items:
  - id: {$itemId}
    occurred_at: 2026-03-06T08:00:00+00:00
    name: Oats
    meal: breakfast
    quantity: 1
    units: serving
    calories: 300
---
MD);

    $this->artisan('content:sync', ['path' => $path])->assertSuccessful();

    $calorie = Calorie::on('content')->where('ulid', $itemId)->first();

    expect($calorie)->not->toBeNull()
        ->and($calorie->name)->toBe('Oats')
        ->and($calorie->calories)->toBe(300)
        ->and(File::exists($path))->toBeTrue();

    File::delete($this->contentDb);
});
