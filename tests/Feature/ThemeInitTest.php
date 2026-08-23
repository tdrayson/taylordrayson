<?php

use App\Support\Preferences;

use function Pest\Laravel\get;

it('resolves the scheme before first paint without a flash', function () {
    $html = get('/')->assertOk()->getContent();

    // The server renders the class from the cookie, so the script only has to
    // cover what the server cannot know: a first visit, and a 'system' visitor
    // whose OS scheme changed since the cookie was written.
    expect($html)
        ->toContain('prefers-color-scheme: dark')
        ->toContain('document.cookie.match(/(?:^|;\\s*)theme=([^;]*)/)');
});

it('reports the resolved scheme back so the next request renders it', function () {
    // Without this a 'system' visitor gets light server-side every time.
    expect(get('/')->assertOk()->getContent())->toContain("document.cookie = 'scheme='");
});

it('needs no script at all once an explicit choice is stored', function () {
    $dark = get('/')->assertOk();

    expect($dark->getContent())->toContain('<html lang="en" class="">');

    $this->withUnencryptedCookies([Preferences::THEME => 'dark'])
        ->get('/')
        ->assertSee('<html lang="en" class="dark"', false);
});
