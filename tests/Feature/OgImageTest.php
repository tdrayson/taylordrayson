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

it('serves a cached og card as a png for each url variant', function (string $url, array $seed) {
    seedCard(...$seed);

    $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
})->with([
    'explicit title' => ['/og.png?title=Hello world', ['title' => 'Hello world']],
    'default title fallback' => ['/og.png', ['title' => 'Taylor Drayson']],
    'invalid accent falls back to brand default' => ['/og.png?title=Hi&accent=not-a-hex', ['title' => 'Hi']],
    'eyebrow and accent in cache key' => [
        '/og.png?title=Morning Run&eyebrow=Activity&accent=2e9e6a',
        ['title' => 'Morning Run', 'eyebrow' => 'Activity', 'accent' => '2e9e6a'],
    ],
    'date in cache key' => [
        '/og.png?title=A walk&eyebrow=Activity&accent=2e9e6a&date=Mon 9 Jun 2025',
        ['title' => 'A walk', 'eyebrow' => 'Activity', 'accent' => '2e9e6a', 'date' => 'Mon 9 Jun 2025'],
    ],
    'branded home variant' => ['/og.png?variant=home', ['title' => 'Taylor Drayson', 'layout' => 'home']],
]);

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

it('renders the sleep stage bar with each stage segment', function () {
    $html = view('og.card', [
        'layout' => 'text',
        'accent' => '6a5acd',
        'eyebrow' => 'Sleep',
        'title' => 'I slept 7h 32m',
        'date' => 'Wed 25 Jun 2026',
        'cutout' => '',
        'stages' => [
            ['label' => 'Awake', 'color' => '#ea8686', 'percent' => 6],
            ['label' => 'REM', 'color' => '#5494d4', 'percent' => 24],
            ['label' => 'Light', 'color' => '#9fbfdf', 'percent' => 49],
            ['label' => 'Deep', 'color' => '#5247c2', 'percent' => 21],
        ],
    ])->render();

    expect($html)->toContain('class="stage-bar"')
        ->toContain('width: 49%')
        ->toContain('#5247c2')
        ->toContain('Deep');
});

it('omits the sleep stage bar when there are no stages', function () {
    $html = view('og.card', [
        'layout' => 'text',
        'accent' => '6a5acd',
        'eyebrow' => 'Note',
        'title' => 'A quick thought',
        'date' => 'Wed 25 Jun 2026',
        'cutout' => '',
    ])->render();

    expect($html)->not->toContain('class="stage-bar"');
});

it('clears cached og cards with og:clear', function () {
    Storage::fake('local');
    Storage::disk('local')->put('og/card.png', 'bytes');
    Storage::disk('local')->put('og/entry/entry.png', 'bytes');

    $this->artisan('og:clear')->assertSuccessful();

    expect(Storage::disk('local')->exists('og/card.png'))->toBeFalse()
        ->and(Storage::disk('local')->exists('og/entry/entry.png'))->toBeFalse();
});
