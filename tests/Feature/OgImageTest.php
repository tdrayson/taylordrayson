<?php

use App\Http\Controllers\OgImageController;
use App\Models\Activity;
use App\Support\OgRenderer;
use Illuminate\Support\Facades\Storage;

/**
 * Mirrors OgImageController: cards live under og/<generation>/, named md5 of
 * "layout|title|eyebrow|date|accent|subtitle". Seeding the cached file
 * lets us exercise routing, input handling, and serving without invoking
 * Browsershot (which needs Chromium), so it has to stay in step with the
 * controller: a stale key here does not fail, it quietly renders for real.
 */
function seedCard(string $title, string $eyebrow = '', string $accent = '3858e9', string $layout = 'text', string $date = '', string $subtitle = ''): void
{
    Storage::fake('local');
    $hash = md5(implode('|', [$layout, $title, $eyebrow, $date, $accent, $subtitle]));
    Storage::disk('local')->put('og/'.OgRenderer::generation()."/{$hash}.png", 'fake-png-bytes');
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
    'branded home variant falls back to the tagline' => [
        '/og.png?variant=home',
        ['title' => 'Taylor Drayson', 'layout' => 'home', 'subtitle' => OgImageController::TAGLINE],
    ],
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

it('keys the home card on the description it was given', function () {
    seedCard('Taylor Drayson', layout: 'home', subtitle: 'Everything I log, newest first.');

    $this->get('/og.png?variant=home&title=Taylor+Drayson&description=Everything+I+log%2C+newest+first.')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('ignores the cache-busting param when keying a card', function () {
    seedCard('Hello world');

    // The param exists to move the URL, not the card: folding it into the key
    // would re-render every card on every bust for identical bytes.
    $this->get('/og.png?title=Hello world&v=whatever')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('stamps an entry card url with the design and the entry it describes', function () {
    $activity = Activity::factory()->create(['name' => 'Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 07:30:00']);
    $url = fn (): string => $this->get('/2026/03/15/'.$activity->slug())
        ->assertOk()
        ->viewData('page')['props']['og']['image'];

    $before = $url();

    expect($before)->toContain('v='.OgRenderer::generation());

    $this->travel(1)->hour();
    $activity->touch();

    // The URL is what a share preview refetches by, so an edited entry has to
    // stop pointing at the card built from what it used to say.
    expect($url())->not->toBe($before);
});
