<?php

it("lists an entry's formats behind a quiet footer trigger", function () {
    $flight = krkToLgw();

    $page = visit($flight->url())
        ->assertSee('Also as')
        ->assertDontSee('The route')
        ->click('[aria-label="Show other formats"]')
        ->assertSee('.geojson')
        ->assertSee('The route');

    // A row must be a real anchor to a file, not an Inertia visit: the
    // href ends in an extension Inertia's client router would try (and
    // fail) to parse as a page response.
    $page->assertPresent('[role="menu"] a[href$=".geojson"][target="_blank"][rel="noopener"]');
});
