<?php

// The dynamic icon maps (navigation.js / entryTypes.js) store registry-name
// STRINGS rather than imported hugeicons objects; Icon.vue resolves a string via
// the registry. This guards that a map-driven icon still renders (a broken name
// would resolve to null and render nothing, without erroring), using the command
// palette whose result rows render pageCommands/archiveCommands icons.
it('renders map-driven registry-name icons as svg', function () {
    $page = visit('/')->resize(1280, 900);

    $page->click('Search')
        ->type('[role="dialog"] input', 'Calendar')
        ->assertScript(
            "[...document.querySelectorAll('[role=\"dialog\"] button svg')].length > 0",
            true,
        );
});
