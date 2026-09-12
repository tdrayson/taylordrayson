<?php

use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Note;

it('says what a kudo was and where it came from', function () {
    $note = Note::factory()->create(['occurred_at' => now()->subHour()]);

    $note->syndicatedResponses()->create([
        'source' => Source::Strava->value,
        'kind' => WebmentionKind::Like,
        'author_name' => 'Justin M.',
        'occurred_at' => now(),
    ]);

    visit($note->url())
        ->assertSee('Justin M.')
        ->assertSee('gave kudos')
        ->assertSee('Strava')
        // assertSee matches case-insensitively, so the byline's capitalisation
        // ("via Strava", not "via strava") needs an exact-case DOM check.
        ->assertScript("document.body.textContent.includes('via Strava')", true)
        ->assertScript("document.body.textContent.includes('via strava')", false)
        ->assertNoJavascriptErrors();
});
