<?php

use function Pest\Laravel\get;

it('renders the SleepScore component when the sleep-score page is requested', function () {
    get('/sleep-score')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('SleepScore'));
});
