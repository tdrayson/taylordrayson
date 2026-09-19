<?php

use App\Models\Note;

it('advertises every supported format in the head', function () {
    $flight = krkToLgw();

    $page = visit($flight->url())->assertPresent('link[rel="alternate"][type="application/json; charset=utf-8"]');

    $page->assertPresent('link[rel="alternate"][type="application/geo+json; charset=utf-8"]')
        ->assertPresent('link[rel="alternate"][type="text/calendar; charset=utf-8"]');
});

it('does not advertise a format the entry cannot serve', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-06-08 10:00:00']);

    visit($note->url())->assertNotPresent('link[rel="alternate"][type="application/geo+json; charset=utf-8"]');
});
