<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\TimelineEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('passes on a healthy database', function () {
    Note::factory()->create(['occurred_at' => '2025-05-01 10:00:00']);

    $this->artisan('db:check')->assertSuccessful();
});

it('finds a spine row whose content was deleted behind the observer', function () {
    $note = Note::factory()->create(['occurred_at' => '2025-05-01 10:00:00']);

    // A raw delete, the one path that skips the model events the observer needs.
    DB::table('notes')->where('id', $note->id)->delete();

    $this->artisan('db:check')
        ->expectsOutputToContain('Note: 1')
        ->assertFailed();

    expect(TimelineEntry::count())->toBe(1);

    $this->artisan('db:check --prune')->assertSuccessful();

    expect(TimelineEntry::count())->toBe(0);
});

it('finds content that never reached the spine', function () {
    $note = Note::factory()->create(['occurred_at' => '2025-05-01 10:00:00']);
    TimelineEntry::query()->delete();

    $this->artisan('db:check')
        ->expectsOutputToContain('Notes: 1')
        ->assertFailed();

    // Pruning must not touch these: the note is real, it is the entry that is
    // missing, and re-saving the note is what rebuilds it.
    $this->artisan('db:check --prune');

    expect(Note::whereKey($note->id)->exists())->toBeTrue();
});

it('finds two entries claiming one URL on the same day', function () {
    Note::factory()->create(['occurred_at' => '2025-05-01 10:00:00']);
    Note::factory()->create(['occurred_at' => '2025-05-01 14:00:00']);

    // The observer suffixes collisions, so force one to prove the check works.
    $second = TimelineEntry::query()->orderByDesc('id')->first();
    DB::table('timeline_entries')
        ->where('id', $second->id)
        ->update(['url_slug' => TimelineEntry::query()->orderBy('id')->first()->url_slug]);

    $this->artisan('db:check')
        ->expectsOutputToContain('claimed 2 times')
        ->assertFailed();
});

it('finds an attachment whose file was deleted from disk', function () {
    Storage::fake('public');

    $note = Note::factory()->create(['occurred_at' => '2025-05-01 10:00:00']);
    $note->addMedia(UploadedFile::fake()->image('photo.jpg', 20, 20))
        ->toMediaCollection('photos');

    $attachment = Attachment::firstOrFail();

    $this->artisan('db:check')->assertSuccessful();

    // The row stays, so nothing but the disk can see this.
    Storage::disk('public')->delete($attachment->getPathRelativeToRoot());

    $this->artisan('db:check')
        ->expectsOutputToContain('Note: 1 file')
        ->assertFailed();
});

it('finds a card conversion that was never written', function () {
    Storage::fake('public');

    $note = Note::factory()->create(['occurred_at' => '2025-05-01 10:00:00']);
    $note->addMedia(UploadedFile::fake()->image('photo.jpg', 20, 20))
        ->toMediaCollection('photos');

    $attachment = Attachment::firstOrFail();

    // What the real failure looked like: the database recorded the conversion
    // as generated, so --only-missing skipped it forever, but no file existed.
    $attachment->generated_conversions = ['card' => true];
    $attachment->save();
    Storage::disk('public')->delete($attachment->getPathRelativeToRoot('card'));

    $this->artisan('db:check')
        ->expectsOutputToContain('Note: 1 conversion')
        ->assertFailed();
});
