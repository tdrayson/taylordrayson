<?php

// The links used to be rendered in the Blade root, which an Inertia visit never
// re-renders: /films reached from /flights still advertised the flight feed.
it('rewrites the contextual feed links on a client-side navigation', function () {
    $hrefs = "[...document.querySelectorAll('link[rel=\"alternate\"][href*=\"types=\"]')].map(l => l.getAttribute('href')).join(' ')";

    $page = visit('/more');

    $page->assertScript($hrefs, '');

    $page->click('a[href="/films"]')
        ->assertPathIs('/films')
        ->assertScript($hrefs, '/feed?types=film /feed/rss?types=film /feed/json?types=film');

    $page->click('a[href="/more"]')
        ->assertPathIs('/more')
        ->click('a[href="/flights"]')
        ->assertPathIs('/flights')
        ->assertScript($hrefs, '/feed?types=flight /feed/rss?types=flight /feed/json?types=flight')
        ->assertNoJavascriptErrors();
});
