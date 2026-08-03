<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function writeEventPhoto(string $dir, string $name): void
{
    $image = imagecreatetruecolor(20, 20);
    imagejpeg($image, $dir.'/'.$name);
    imagedestroy($image);
}

it('matches event photos by name, disambiguating recurring events and dropped punctuation', function () {
    Storage::fake('public');

    $dir = sys_get_temp_dir().'/evt-photos-'.uniqid();
    mkdir($dir);

    // Colon in the title, dropped from the filename.
    $miracle = Event::factory()->create(['name' => 'Derren Brown: Miracle', 'occurred_at' => '2016-01-07']);
    // Year is part of the name, not a disambiguator.
    $wordcamp = Event::factory()->create(['name' => 'WordCamp Europe 2024', 'occurred_at' => '2024-06-13']);
    // Same name twice: the trailing year picks the recurrence.
    $bttf2020 = Event::factory()->create(['name' => 'Back to the Future: The Musical', 'occurred_at' => '2020-03-10']);
    $bttf2022 = Event::factory()->create(['name' => 'Back to the Future: The Musical', 'occurred_at' => '2022-04-20']);
    // Two photos of one event via the numeric suffix.
    $oliver = Event::factory()->create(['name' => 'Oliver!', 'occurred_at' => '2025-03-05']);

    writeEventPhoto($dir, 'Derren Brown Miracle.jpg');
    writeEventPhoto($dir, 'WordCamp Europe 2024.jpg');
    writeEventPhoto($dir, 'Back to the Future The Musical 2020.jpg');
    writeEventPhoto($dir, 'Oliver!.jpg');
    writeEventPhoto($dir, 'Oliver! 1.jpg');

    $this->artisan('events:import-photos', ['folder' => $dir, '--apply' => true])->assertSuccessful();

    expect($miracle->fresh()->getMedia('photos'))->toHaveCount(1)
        ->and($wordcamp->fresh()->getMedia('photos'))->toHaveCount(1)
        ->and($bttf2020->fresh()->getMedia('photos'))->toHaveCount(1)
        // The 2022 recurrence must not receive the 2020 photo.
        ->and($bttf2022->fresh()->getMedia('photos'))->toHaveCount(0)
        ->and($oliver->fresh()->getMedia('photos'))->toHaveCount(2);

    // Idempotent: a second run attaches nothing new.
    $this->artisan('events:import-photos', ['folder' => $dir, '--apply' => true])->assertSuccessful();
    expect($oliver->fresh()->getMedia('photos'))->toHaveCount(2);

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);
});

it('does not attach anything on a dry run', function () {
    Storage::fake('public');

    $dir = sys_get_temp_dir().'/evt-photos-'.uniqid();
    mkdir($dir);

    $event = Event::factory()->create(['name' => 'Hamilton', 'occurred_at' => '2025-06-14']);
    writeEventPhoto($dir, 'Hamilton 2025-06-14.jpg');

    $this->artisan('events:import-photos', ['folder' => $dir])->assertSuccessful();

    expect($event->fresh()->getMedia('photos'))->toHaveCount(0);

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);
});
