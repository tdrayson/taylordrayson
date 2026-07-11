<?php

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Pest.php only binds TestCase + RefreshDatabase to the Feature directory;
// bind them locally here so this Browser test boots the app and gets a
// clean database per run, same as a Feature test would.
uses(TestCase::class, RefreshDatabase::class);

it('renders content emoji as a Twemoji image on the timeline', function () {
    Note::factory()->create([
        'content' => 'Great workout today 👍',
        'occurred_at' => now()->subHour(),
    ]);

    $page = visit('/');

    $page->assertScript("document.querySelectorAll('img.emoji').length > 0", true)
        ->assertScript(
            "!!document.querySelector('img.emoji') && document.querySelector('img.emoji').src.includes('/twemoji/')",
            true,
        );
});

it('renders content emoji as a Twemoji image on a note detail page', function () {
    $note = Note::factory()->create([
        'content' => 'Great workout today 👍',
        'occurred_at' => now()->subDay(),
    ]);

    $page = visit($note->fresh()->url());

    $page->assertScript("document.querySelectorAll('img.emoji').length > 0", true)
        ->assertScript(
            "!!document.querySelector('img.emoji') && document.querySelector('img.emoji').src.includes('/twemoji/')",
            true,
        );
});
