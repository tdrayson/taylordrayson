<?php

use App\Models\Note;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->contentPath = storage_path('framework/testing/content-'.uniqid('', true));
    File::ensureDirectoryExists($this->contentPath);
    config(['content.path' => $this->contentPath]);
});

afterEach(function () {
    if (isset($this->contentPath) && File::isDirectory($this->contentPath)) {
        File::deleteDirectory($this->contentPath);
    }
});

it('writes a markdown file when a note is created', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-07-12 09:15:00',
        'content' => 'Hello flat file',
        'slug' => 'hello',
        'timezone' => 'Europe/London',
    ]);

    $path = $this->contentPath.'/2026/07/12/hello.md';

    expect(File::exists($path))->toBeTrue()
        ->and($note->ulid)->not->toBeEmpty()
        ->and(File::get($path))
        ->toContain('id: '.$note->ulid)
        ->toContain('type: note')
        ->toContain('slug: hello')
        ->toContain('timezone: Europe/London')
        ->toContain('Hello flat file');
});

it('moves the file when the note date or slug changes', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-07-12 09:15:00',
        'content' => 'Moving soon',
        'slug' => 'old-slug',
    ]);

    $oldPath = $this->contentPath.'/2026/07/12/old-slug.md';
    expect(File::exists($oldPath))->toBeTrue();

    $note->update([
        'occurred_at' => '2026-07-13 10:00:00',
        'slug' => 'new-slug',
        'content' => 'Moved',
    ]);

    expect(File::exists($oldPath))->toBeFalse()
        ->and(File::exists($this->contentPath.'/2026/07/13/new-slug.md'))->toBeTrue()
        ->and(File::get($this->contentPath.'/2026/07/13/new-slug.md'))->toContain('Moved');
});

it('deletes the file when the note is deleted', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-07-12 09:15:00',
        'slug' => 'gone',
        'content' => 'Bye',
    ]);

    $path = $this->contentPath.'/2026/07/12/gone.md';
    expect(File::exists($path))->toBeTrue();

    $note->delete();

    expect(File::exists($path))->toBeFalse();
});

it('assigns a ulid before persisting', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-07-12 09:15:00',
        'content' => 'Has identity',
    ]);

    expect($note->ulid)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/i');
});
