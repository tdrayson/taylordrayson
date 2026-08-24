<?php

it('301s every legacy path to its current one', function () {
    expect(config('redirects'))->not->toBeEmpty();

    foreach (config('redirects') as $from => $to) {
        $this->get("/{$from}")->assertRedirect("/{$to}");
        $this->get("/{$from}")->assertStatus(301);
    }
});

it('leaves a live sub-route of a redirected path alone', function () {
    // /vehicles is a dead index but /vehicles/{value} is the fuel taxonomy, so
    // the redirect has to match the exact path and nothing beneath it.
    $this->get('/vehicles/hn14wxp')->assertSuccessful();
});

it('sends every legacy path somewhere that actually resolves', function () {
    foreach (config('redirects') as $to) {
        $this->get("/{$to}")->assertSuccessful();
    }
});
