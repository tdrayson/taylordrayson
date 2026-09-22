<?php

it('turns page copy into numeronyms and puts it back', function () {
    $page = visit('/')->resize(1280, 800);
    $sidebarLink = "document.querySelector('nav a[href=\"/more\"]').textContent.trim()";

    $page->assertScript($sidebarLink, 'More')
        ->click('[aria-label="Open settings"]')
        ->click('[aria-labelledby="numeronym-mode-label"]')
        ->assertScript($sidebarLink, 'M2e')
        ->assertScript("document.getElementById('numeronym-mode-label').textContent", 'Numeronym mode')
        ->assertScript(cookieValue('pref_numeronym'), 'on')
        ->click('[aria-labelledby="numeronym-mode-label"]')
        ->assertScript($sidebarLink, 'More');
});
