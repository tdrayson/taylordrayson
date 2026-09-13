<?php

use App\Models\Flight;

it('renders the more page grouped by kind headings', function () {
    Flight::factory()->create();

    $page = visit('/more');

    $page->assertPresent('h2.font-display')
        ->assertSeeIn('h2.font-display', 'Writing')
        ->assertSeeIn('h2.font-display', 'Travel')
        ->assertSeeIn('h2.font-display', 'Life & health')
        ->assertSeeIn('h2.font-display', 'Going out')
        ->assertSeeIn('h2.font-display', 'Speaking')
        ->screenshot(true, 'more-kind-groups');
});
