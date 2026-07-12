<?php

use App\Content\EntryFileRepository;
use App\Models\Activity;
use App\Models\Note;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function ulidMediaJpeg(): string
{
    $image = imagecreatetruecolor(8, 8);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

beforeEach(function () {
    $this->contentPath = storage_path('framework/testing/content-media-'.uniqid('', true));
    File::ensureDirectoryExists($this->contentPath);
    config(['content.path' => $this->contentPath]);
    Storage::fake('public');
});

afterEach(function () {
    if (isset($this->contentPath) && File::isDirectory($this->contentPath)) {
        File::deleteDirectory($this->contentPath);
    }
});

it('stores media under media/{entry-ulid}/', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-03-10 09:00:00',
        'slug' => 'with-a-photo',
        'content' => 'With a photo',
    ]);

    $note->addMediaFromString(ulidMediaJpeg())
        ->usingFileName('cover.jpg')
        ->toMediaCollection('cover');

    $media = $note->refresh()->getFirstMedia('cover');

    expect($media->getPathRelativeToRoot())->toStartWith('media/'.$note->ulid.'/')
        ->and(Storage::disk('public')->exists('media/'.$note->ulid.'/cover.jpg'))->toBeTrue();
});

it('writes photo filenames into flat-file frontmatter', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-03-11 07:00:00',
        'type' => 'Run',
        'name' => 'Photo Run',
    ]);

    $activity->addMediaFromString(ulidMediaJpeg())
        ->usingFileName('cover.jpg')
        ->toMediaCollection('cover');

    $activity->addMediaFromString(ulidMediaJpeg())
        ->usingFileName('photo-1.jpg')
        ->toMediaCollection('photos');

    $path = $this->contentPath.'/2026/03/11/photo-run.md';
    $parsed = app(EntryFileRepository::class)->parse(File::get($path));

    expect($parsed['frontmatter']['photos'])->toBe(['cover.jpg', 'photo-1.jpg']);
});

it('removes photos from frontmatter when media is deleted', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-03-12 10:00:00',
        'slug' => 'cleared-photo',
        'content' => 'Gone',
    ]);

    $note->addMediaFromString(ulidMediaJpeg())
        ->usingFileName('cover.jpg')
        ->toMediaCollection('cover');

    $path = $this->contentPath.'/2026/03/12/cleared-photo.md';
    expect(app(EntryFileRepository::class)->parse(File::get($path))['frontmatter'])
        ->toHaveKey('photos');

    $note->clearMediaCollection('cover');

    expect(app(EntryFileRepository::class)->parse(File::get($path))['frontmatter'])
        ->not->toHaveKey('photos');
});
