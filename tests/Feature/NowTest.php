<?php

use function Pest\Laravel\get;

it('renders the now page via Inertia', function () {
    get('/now')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Now'));
});
