<?php

/*
 * The menu primitive's behaviour is the point of extracting it, so it is tested
 * through a real consumer rather than in isolation.
 */

it('opens the time jump menu and closes it on Escape', function () {
    $page = visit('/');

    $page->assertDontSee('On this day')
        ->click('[aria-label="Open time navigation"]')
        ->assertSee('On this day')
        ->keys('[role="menu"]', 'Escape')
        ->assertDontSee('On this day');
});
