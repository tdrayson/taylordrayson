<?php

use Illuminate\Support\Facades\Storage;

/**
 * The hash mirrors OgImageController: md5 of "version|layout|title|eyebrow|date|accent".
 * Seeding the cached file lets us exercise routing, input handling, and serving
 * without invoking Browsershot (which needs Chromium).
 */
function seedCard(string $title, string $eyebrow = '', string $accent = '3858e9', string $layout = 'text', string $date = ''): void
{
    Storage::fake('local');
    $hash = md5(implode('|', [config('og.version'), $layout, $title, $eyebrow, $date, $accent]));
    Storage::disk('local')->put("og/{$hash}.png", 'fake-png-bytes');
}

it('serves a cached og card as a png', function () {
    seedCard('Hello world');

    $this->get('/og.png?title=Hello world')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('falls back to the default title when none is given', function () {
    seedCard('Taylor Drayson');

    $this->get('/og.png')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('ignores an invalid accent and uses the brand default', function () {
    seedCard('Hi');

    $this->get('/og.png?title=Hi&accent=not-a-hex')->assertOk();
});

it('uses a provided eyebrow and accent in the cache key', function () {
    seedCard('Morning Run', 'Activity', '2e9e6a');

    $this->get('/og.png?title=Morning Run&eyebrow=Activity&accent=2e9e6a')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('includes the date in the cache key', function () {
    seedCard('A walk', 'Activity', '2e9e6a', 'text', 'Mon 9 Jun 2025');

    $this->get('/og.png?title=A walk&eyebrow=Activity&accent=2e9e6a&date=Mon 9 Jun 2025')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('serves the branded home variant card', function () {
    seedCard('Taylor Drayson', '', '3858e9', 'home');

    $this->get('/og.png?variant=home')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('404s the per-entry card for an unknown entry', function () {
    $this->get('/og/entry/999999.png')->assertNotFound();
});

it('regenerates cards when the og version changes', function () {
    seedCard('Hello world');

    // The seeded file is keyed to the current version, so bumping the version
    // points the route at a different (missing) path.
    config(['og.version' => '1']);
    $this->get('/og.png?title=Hello world')->assertOk();
});

it('clears cached og cards with og:clear', function () {
    Storage::fake('local');
    Storage::disk('local')->put('og/card.png', 'bytes');
    Storage::disk('local')->put('og/entry/entry.png', 'bytes');

    $this->artisan('og:clear')->assertSuccessful();

    expect(Storage::disk('local')->exists('og/card.png'))->toBeFalse()
        ->and(Storage::disk('local')->exists('og/entry/entry.png'))->toBeFalse();
});
