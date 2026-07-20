<?php

it('renders the design-system page', function () {
    $this->get('/design-system')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('DesignSystem')->has('og'));
});

it('renders the sleep-score page', function () {
    $this->get('/sleep-score')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('SleepScore')->where('og.title', 'How the sleep score works'));
});

it('renders the leaderboard page', function () {
    $this->get('/leaderboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Leaderboard')->has('og')->has('entries'));
});
