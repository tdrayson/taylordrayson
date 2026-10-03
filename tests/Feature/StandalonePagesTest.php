<?php

it('renders the design-system page', function () {
    $this->get('/design-system')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('DesignSystem')->where('head.noindex', true));
});

it('renders the leaderboard page', function () {
    $this->get('/leaderboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Leaderboard')->where('head.title', 'Leaderboard')->has('entries'));
});
