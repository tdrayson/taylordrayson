<?php

use App\Models\Note;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->contentPath = storage_path('framework/testing/content-sync-'.uniqid('', true));
    $this->contentDb = storage_path('framework/testing/content-index-'.uniqid('', true).'.sqlite');
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

it('reindexes a note file via content:sync', function () {
    $ulid = '01JTESTCONTENTSYNC0000000000';
    $path = $this->contentPath.'/2026/07/12/hand-edited.md';
    File::ensureDirectoryExists(dirname($path));
    File::put($path, <<<MD
---
id: {$ulid}
type: note
occurred_at: 2026-07-12T09:15:00+01:00
timezone: Europe/London
slug: hand-edited
---

Edited on disk
MD);

    $this->artisan('content:sync', ['path' => $path])->assertSuccessful();

    $note = Note::on('content')->where('ulid', $ulid)->first();

    expect($note)->not->toBeNull()
        ->and($note->content)->toBe('Edited on disk')
        ->and($note->slug)->toBe('hand-edited')
        ->and($note->timezone)->toBe('Europe/London');
});

it('warms all note files from the content tree into content.sqlite', function () {
    $path = $this->contentPath.'/2026/07/11/warmed.md';
    File::ensureDirectoryExists(dirname($path));
    File::put($path, <<<'MD'
---
id: 01JTESTCONTENTWARM000000000
type: note
occurred_at: 2026-07-11T08:00:00+00:00
slug: warmed
---

From warm
MD);

    $this->artisan('content:cache:warm')->assertSuccessful();

    expect(Note::on('content')->where('ulid', '01JTESTCONTENTWARM000000000')->exists())->toBeTrue()
        ->and(File::exists($this->contentDb))->toBeTrue();
});
