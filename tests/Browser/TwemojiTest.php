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
            "(() => { const img = document.querySelector('img.emoji'); return !!img && img.src.includes('cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/') && img.complete && img.naturalWidth > 0; })()",
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
            "(() => { const img = document.querySelector('img.emoji'); return !!img && img.src.includes('cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/') && img.complete && img.naturalWidth > 0; })()",
            true,
        );
});
