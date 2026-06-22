<?php

use function Pest\Laravel\get;

it('renders the design system page via Inertia', function () {
    get('/design-system')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('DesignSystem'));
});
