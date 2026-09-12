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
        ->assertNoJavascriptErrors();
});
