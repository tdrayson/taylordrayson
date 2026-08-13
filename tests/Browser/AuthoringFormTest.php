<?php

use App\Models\User;

it('draws every primary field as an input, not a chip', function () {
    $this->actingAs(User::factory()->create());

    // Asserting the rendered DOM, not the Inertia props: a chip would satisfy
    // a text assertion while the field was still collapsed.
    visit('/new/fuel')
        ->assertPresent('#cost')
        ->assertPresent('#price_per_litre')
        ->assertPresent('#occurred_at')
        ->assertPresent('#vehicle_id')
        ->assertNoJavascriptErrors();
});
