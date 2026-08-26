<?php

use App\Models\Calorie;
use App\Models\Note;
use App\Models\Sleep;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

function notePhotoJpegBytes(): string
{
    $image = imagecreatetruecolor(800, 600);
    imagefilledrectangle($image, 0, 0, 799, 599, imagecolorallocate($image, 40, 140, 90));
    ob_start();
    imagejpeg($image, null, 80);

    return (string) ob_get_clean();
}

it('shows the wake time on sleep cards and entry pages', function () {
    $sleep = Sleep::factory()->create([
        'occurred_at' => now()->startOfDay()->setTime(7, 24),
        'bedtime' => now()->startOfDay()->subHours(2),
        'wake_time' => now()->startOfDay()->setTime(7, 24),
    ]);

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.iconKey', 'sleep')
        ->where('groups.0.items.0.time', '7:24am'));

    get($sleep->fresh()->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('occurredLabel', now()->startOfDay()->format('D j M Y').', 7:24am'));
});

it('shows a standardised midnight time on food cards', function () {
    Calorie::factory()->create(['occurred_at' => now()->startOfDay()]);

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.iconKey', 'calorie')
        ->where('groups.0.items.0.time', '12:00am'));
});

it('exposes note photos on the timeline card and entry payload', function () {
    Storage::fake('public');

    $note = Note::factory()->create(['occurred_at' => now()->subHour()]);
    $note->addMediaFromString(notePhotoJpegBytes())->usingFileName('note.jpg')->toMediaCollection('photos');

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.iconKey', 'note')
        ->has('groups.0.items.0.photos.0.src'));

    get($note->fresh()->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('entry.photos.0.src')
            ->has('entry.photos.0.full'));
});

it('leaves note card photos empty when the note has none', function () {
    Note::factory()->create(['occurred_at' => now()->subHour()]);

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.iconKey', 'note')
        ->where('groups.0.items.0.photos', []));
});
