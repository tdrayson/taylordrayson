<?php

use App\Content\ContentIndex;
use App\Content\ContentTypes;
use App\Contracts\DefinesContentSchema;
use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Note;
use App\Models\TimelineEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->contentPath = storage_path('framework/testing/content-index-warm-'.uniqid('', true));
    $this->contentDb = storage_path('framework/testing/content-index-db-'.uniqid('', true).'.sqlite');
    File::ensureDirectoryExists($this->contentPath);
    config([
        'content.path' => $this->contentPath,
        'database.connections.content.database' => $this->contentDb,
    ]);
    DB::purge('content');
});

afterEach(function () {
    if (isset($this->contentPath) && File::isDirectory($this->contentPath)) {
        File::deleteDirectory($this->contentPath);
    }

    if (isset($this->contentDb) && File::exists($this->contentDb)) {
        File::delete($this->contentDb);
    }
});

it('rebuilds content.sqlite from a fixture tree and supports timeline queries', function () {
    File::ensureDirectoryExists($this->contentPath.'/2026/04/01');
    File::put($this->contentPath.'/2026/04/01/hello.md', <<<'MD'
---
id: 01JTESTINDEXNOTE00000000000
type: note
occurred_at: 2026-04-01T09:00:00+00:00
slug: hello
---

Hello index
MD);

    File::put($this->contentPath.'/2026/04/01/morning-run.md', <<<'MD'
---
id: 01JTESTINDEXACT000000000000
type: activity
occurred_at: 2026-04-01T07:00:00+00:00
slug: morning-run
kind: Run
name: Morning Run
distance: 5000
---

Easy
MD);

    File::put($this->contentPath.'/2026/04/01/calories.md', <<<'MD'
---
id: 01JTESTINDEXCALDAY000000000
type: calorie
occurred_at: 2026-04-01T12:00:00+00:00
slug: calories
items:
  - id: 01JTESTINDEXCALITEM00000000
    occurred_at: 2026-04-01T08:00:00+00:00
    name: Oats
    meal: breakfast
    quantity: 1
    units: serving
    calories: 300
---
MD);

    $this->artisan('content:cache:warm')->assertSuccessful();

    expect(Note::on('content')->count())->toBe(1)
        ->and(Activity::on('content')->count())->toBe(1)
        ->and(Calorie::on('content')->count())->toBe(1)
        ->and(TimelineEntry::on('content')->count())->toBeGreaterThanOrEqual(2)
        ->and(TimelineEntry::on('content')->coveringDate('2026-04-01')->count())->toBeGreaterThanOrEqual(2);

    $this->artisan('content:bench', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"step": "warm"');
});

it('clears the content sqlite file', function () {
    app(ContentIndex::class)->migrate();
    expect(File::exists($this->contentDb))->toBeTrue();

    $this->artisan('content:cache:clear')->assertSuccessful();

    expect(File::exists($this->contentDb))->toBeFalse();
});

it('builds index tables from each model schema method', function () {
    expect(ContentIndex::models())->toBe(ContentTypes::indexModels())
        ->and(ContentIndex::models())->each->toBeString();

    foreach (ContentIndex::models() as $class) {
        expect(is_a($class, DefinesContentSchema::class, true))->toBeTrue(
            "{$class} must implement DefinesContentSchema",
        );
    }

    app(ContentIndex::class)->migrate();

    expect(Schema::connection('content')->hasTable('notes'))->toBeTrue()
        ->and(Schema::connection('content')->hasTable('timeline_entries'))->toBeTrue()
        ->and(Schema::connection('content')->hasTable('taggables'))->toBeTrue()
        ->and(Schema::connection('content')->hasColumn('notes', 'ulid'))->toBeTrue();
});
