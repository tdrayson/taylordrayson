<?php

it('renders the design-system page', function () {
    $this->get('/design-system')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('DesignSystem')->has('og'));
});

it('renders the leaderboard page', function () {
    $this->get('/leaderboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Leaderboard')->has('og')->has('entries'));
});
