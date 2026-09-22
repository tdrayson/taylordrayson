<?php

$sidebarLink = "document.querySelector('nav a[href=\"/more\"]').textContent.trim()";

it('rewrites page copy in the chosen mode and puts it back', function () use ($sidebarLink) {
    $page = visit('/')->resize(1280, 800);

    $page->assertScript($sidebarLink, 'More')
        ->click('[aria-label="Open settings"]')
        ->select('#text-mode', 'numeronym')
        ->assertScript($sidebarLink, 'M2e')
        ->assertScript("document.querySelector('label[for=\"text-mode\"]').textContent", 'Text mode')
        ->assertScript(cookieValue('pref_textMode'), 'numeronym')
        ->select('#text-mode', 'reversed')
        ->assertScript($sidebarLink, 'Erom')
        ->select('#text-mode', 'off')
        ->assertScript($sidebarLink, 'More');
});

it('carries an old numeronym switch over to text mode', function () use ($sidebarLink) {
    $page = visit('/')->resize(1280, 800);
    $page->script("document.cookie = 'pref_numeronym=on;path=/'");

    $page->refresh()
        ->assertScript($sidebarLink, 'M2e')
        ->assertScript(cookieValue('pref_textMode'), 'numeronym')
        ->assertScript(cookieValue('pref_numeronym'), null);
});
