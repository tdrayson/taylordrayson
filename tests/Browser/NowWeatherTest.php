<?php

use App\Support\StateStore;

/**
 * The condition slug drives the tile's copy, the tile's icon and the top bar's
 * icon, so these assert the rendered DOM rather than the props, which carry the
 * right slug even when all three render wrong.
 */
beforeEach(function () {
    app(StateStore::class)->put('now.weather', ['condition' => 'mostly-sunny', 'temp' => 24]);
    app(StateStore::class)->put('now.location', ['city' => 'Whyteleafe', 'timezone' => 'Europe/London']);
});

it('describes the sky the phone actually reported, rather than falling back to another one', function () {
    $page = visit('/now')->resize(1280, 800);

    // The fallback was silent: an unmapped condition borrowed partly-cloudy's
    // copy, so a wrong sky read exactly like a working one.
    $page->assertScript("document.querySelector('.weather__headline').textContent.trim()", 'Sun winning, on balance.')
        ->assertScript("document.querySelector('.weather__temp').textContent.trim()", '24°');
});

it('draws the same sky in the top bar as on the Now tile', function () {
    $page = visit('/now')->resize(1280, 800);

    // The two kept separate condition maps: the top bar matched substrings and
    // hit its catch-all "sun" rule, drawing a bare sun, while the tile drew sun
    // behind cloud. Comparing rendered path data is what actually catches them
    // disagreeing; asserting each one's icon separately would not.
    $page->assertScript(<<<'JS'
        (() => {
            const paths = (el) => el && [...el.querySelectorAll('path')].map(p => p.getAttribute('d')).join('|');
            const tile = paths(document.querySelector('.weather__icon'));
            const bar = paths(document.querySelector('.weather-status svg'));

            return Boolean(tile) && tile === bar;
        })()
    JS, true);
});

it('names the sky in words in the top bar tooltip, not as a slug', function () {
    $page = visit('/now')->resize(1280, 800);

    // Both status bars render one, the sidebar's and the top bar's, and only
    // one is on screen at a given width.
    // Read "mostly-sunny in Whyteleafe": the label was built from the raw slug.
    $page->hover('.weather-status:visible')
        ->assertScript("document.querySelector('[role=\"tooltip\"]').textContent.trim()", 'Mostly Sunny in Whyteleafe');
});
