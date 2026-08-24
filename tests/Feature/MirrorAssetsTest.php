<?php

use App\Models\Note;
use Illuminate\Support\Facades\Storage;

/*
 * The asset half of #44: an add-only mirror of originals to R2. Never deletes,
 * and never copies conversions or responsive images, which rebuild from the
 * originals at no API cost.
 */

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('r2');
    config(['filesystems.disks.r2.bucket' => 'test-bucket']);
});

/** A note carrying one attached photo. */
function noteWithPhoto(string $name = 'photo.jpg'): Note
{
    $note = Note::factory()->create();
    $note->addMediaFromString(fakeJpeg())->usingFileName($name)->toMediaCollection('photos');

    return $note->fresh();
}

it('copies an original to the mirror', function () {
    $media = noteWithPhoto()->getFirstMedia('photos');

    $this->artisan('assets:mirror')->assertSuccessful();

    Storage::disk('r2')->assertExists($media->getPathRelativeToRoot());
});

it('never copies conversions or responsive images', function () {
    noteWithPhoto();

    $this->artisan('assets:mirror')->assertSuccessful();

    $mirrored = Storage::disk('r2')->allFiles();

    expect($mirrored)->not->toBeEmpty()
        ->and(collect($mirrored)->filter(fn (string $p): bool => str_contains($p, 'conversions')
            || str_contains($p, 'responsive-images')))->toBeEmpty();
});

// Add-only: a file removed at source stays on the mirror, which is the whole
// point of copy rather than sync.
it('leaves a mirrored file alone when the original is deleted', function () {
    $media = noteWithPhoto();
    $this->artisan('assets:mirror')->assertSuccessful();

    $path = $media->getFirstMedia('photos')->getPathRelativeToRoot();
    $media->clearMediaCollection('photos');

    $this->artisan('assets:mirror')->assertSuccessful();

    Storage::disk('r2')->assertExists($path);
});

it('skips what is already mirrored rather than copying it again', function () {
    noteWithPhoto();
    $this->artisan('assets:mirror')->assertSuccessful();

    $this->artisan('assets:mirror')
        ->expectsOutputToContain('0 copied to the mirror')
        ->assertSuccessful();
});

it('only walks the recent window unless --all is given', function () {
    $old = noteWithPhoto();
    $old->getFirstMedia('photos')->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

    $this->artisan('assets:mirror')->assertSuccessful();
    expect(Storage::disk('r2')->allFiles())->toBeEmpty();

    $this->artisan('assets:mirror --all')->assertSuccessful();
    expect(Storage::disk('r2')->allFiles())->not->toBeEmpty();
});

it('refuses to run when no bucket is configured', function () {
    config(['filesystems.disks.r2.bucket' => null]);

    $this->artisan('assets:mirror')->assertFailed();
});
