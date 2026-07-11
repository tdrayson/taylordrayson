<?php

use function Pest\Laravel\get;

it('renders the no-flash theme init script in the document head', function () {
    $html = get('/')->assertOk()->getContent();

    expect($html)
        ->toContain("localStorage.getItem('theme')")
        ->toContain('prefers-color-scheme: dark')
        ->toContain("classList.add('dark')");
});
