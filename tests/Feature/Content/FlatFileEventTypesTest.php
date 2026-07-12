<?php

use App\Content\EntryFileRepository;
use App\Models\Appearance;
use App\Models\Event;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->contentPath = storage_path('framework/testing/content-events-'.uniqid('', true));
    File::ensureDirectoryExists($this->contentPath);
    config(['content.path' => $this->contentPath]);
});

afterEach(function () {
    if (isset($this->contentPath) && File::isDirectory($this->contentPath)) {
        File::deleteDirectory($this->contentPath);
    }
});

it('writes an appearance file using a title slug and kind field', function () {
    $appearance = Appearance::factory()->create([
        'occurred_at' => '2026-05-01 18:00:00',
        'type' => 'podcast',
        'title' => 'Building Flat Files',
        'show_name' => 'Dev Chat',
        'description' => 'A chat about storage.',
    ]);

    $path = $this->contentPath.'/2026/05/01/building-flat-files.md';

    expect(File::exists($path))->toBeTrue();

    $parsed = app(EntryFileRepository::class)->parse(File::get($path));

    expect($parsed['frontmatter']['type'])->toBe('appearance')
        ->and($parsed['frontmatter']['kind'])->toBe('podcast')
        ->and($parsed['frontmatter']['title'])->toBe('Building Flat Files')
        ->and($parsed['frontmatter']['id'])->toBe($appearance->ulid)
        ->and($parsed['body'])->toContain('A chat about storage.');
});

it('moves an appearance file when the title changes', function () {
    $appearance = Appearance::factory()->create([
        'occurred_at' => '2026-05-01 18:00:00',
        'title' => 'Old Title',
    ]);

    expect(File::exists($this->contentPath.'/2026/05/01/old-title.md'))->toBeTrue();

    $appearance->update(['title' => 'New Title']);

    expect(File::exists($this->contentPath.'/2026/05/01/old-title.md'))->toBeFalse()
        ->and(File::exists($this->contentPath.'/2026/05/01/new-title.md'))->toBeTrue();
});

it('writes an event file with ends_at and kind', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-08-01 09:00:00',
        'ends_at' => '2026-08-03 18:00:00',
        'type' => 'conference',
        'name' => 'Laracon EU',
        'city' => 'Amsterdam',
    ]);

    $path = $this->contentPath.'/2026/08/01/laracon-eu.md';

    expect(File::exists($path))->toBeTrue();

    $parsed = app(EntryFileRepository::class)->parse(File::get($path));

    expect($parsed['frontmatter']['type'])->toBe('event')
        ->and($parsed['frontmatter']['kind'])->toBe('conference')
        ->and($parsed['frontmatter']['name'])->toBe('Laracon EU')
        ->and($parsed['frontmatter']['id'])->toBe($event->ulid)
        ->and($parsed['frontmatter']['ends_at'])->not->toBeEmpty();
});
