<?php

use App\Models\Note;

it("lists an entry's formats in the footer", function () {
    $flight = krkToLgw();

    $page = visit($flight->url())
        ->assertSee('View as')
        ->assertSee('.geojson');

    // A format must be a real anchor to a file, not an Inertia visit: the href
    // ends in an extension Inertia's client router would try (and fail) to
    // parse as a page response.
    $page->assertPresent('a[href$=".geojson"]')
        ->assertMissing('a[href$=".geojson"][data-inertia]');
});

it('offers only the formats an entry actually supports', function () {
    // A note carries no geometry and no span, so neither .geojson nor .ics
    // may be offered; the derived-availability rule is what this guards.
    $note = Note::factory()->create();

    visit($note->url())
        ->assertSee('.json')
        ->assertDontSee('.geojson')
        ->assertDontSee('.ics');
});
