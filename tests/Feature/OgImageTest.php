<?php

use App\Actions\Og\BuildEntryOgData;
use App\Enums\EntryStatus;
use App\Models\Activity;
use App\Models\Note;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Support\OgRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Mirrors OgImageController: a page's card lives at og/<generation>/page/,
 * named md5 of its owner then md5 of "layout|title|eyebrow|accent|subtitle".
 * Seeding it serves the request without Browsershot, so a key out of step with
 * the controller does not fail here, it quietly renders for real.
 */
function seedPageCard(string $owner, string $title, string $eyebrow = '', string $accent = '3858e9', string $layout = 'text', string $subtitle = ''): void
{
    Storage::fake('local');
    $version = md5(implode('|', [$layout, $title, $eyebrow, $accent, $subtitle]));
    Storage::disk('local')->put('og/'.OgRenderer::generation().'/page/'.md5($owner)."-{$version}.png", 'fake-png-bytes');
}

it('serves a page card only on its signed url', function (array $query, array $seed) {
    seedPageCard('/now', ...$seed);

    $url = URL::signedRoute('og', ['for' => '/now', ...$query]);

    $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
    $this->get(str_replace('signature=', 'signature=0', $url))->assertNotFound();
})->with([
    'title' => [['title' => 'Hello world'], ['title' => 'Hello world']],
    'default title' => [[], ['title' => 'Taylor Drayson']],
    'invalid accent falls back to the default' => [['title' => 'Hi', 'accent' => 'not-a-hex'], ['title' => 'Hi']],
    'eyebrow, accent and description' => [
        ['title' => 'Run', 'eyebrow' => 'Activity', 'accent' => '2e9e6a', 'description' => 'Fast.'],
        ['title' => 'Run', 'eyebrow' => 'Activity', 'accent' => '2e9e6a', 'subtitle' => 'Fast.'],
    ],
    'the design token is not part of the key' => [['title' => 'Hi', 'v' => 'whatever'], ['title' => 'Hi']],
]);

it('404s a card url no page signed', function () {
    seedPageCard('/now', 'Hello world');

    $this->get('/og.png?for=/now&title=Hello+world')->assertNotFound();
});

it('points the home page at its signed card, with the bio beneath', function () {
    $image = $this->get('/')->inertiaProps('og.image');

    parse_str((string) parse_url($image, PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'for' => '/',
        'variant' => 'home',
        'description' => config('identity.bio'),
        'v' => OgRenderer::generation(),
    ])->toHaveKey('signature');
});

it('keeps only an owner\'s latest card', function () {
    Storage::fake('local');
    $directory = 'og/'.OgRenderer::generation().'/entry';
    Storage::disk('local')->put("{$directory}/12-1.png", 'old');
    Storage::disk('local')->put("{$directory}/123-1.png", 'another owner');

    $path = app(OgRenderer::class)->card('entry', '12', '2', fn () => view('og.card', [
        'layout' => 'text', 'accent' => '3858e9', 'eyebrow' => null, 'title' => 'Hi', 'date' => null, 'cutout' => '',
    ]));

    expect($path)->toBe(Storage::disk('local')->path("{$directory}/12-2.png"));
    Storage::disk('local')->assertExists("{$directory}/12-2.png");
    Storage::disk('local')->assertMissing("{$directory}/12-1.png");
    Storage::disk('local')->assertExists("{$directory}/123-1.png");
});

it('404s the per-entry card for an unknown entry', function () {
    $this->get('/og/entry/999999.png')->assertNotFound();
});

it('serves a hidden entry\'s card only through the signed url its page emits', function (EntryStatus $status) {
    Storage::fake('local');

    $note = Note::factory()->create([
        'slug' => 'hidden-card',
        'occurred_at' => '2026-06-15 09:00:00',
        'status' => $status,
        'password' => $status === EntryStatus::Private ? 'hunter2' : null,
    ]);
    $entry = TimelineEntry::withoutGlobalScope(ListedScope::class)->where('entry_id', $note->id)->sole();
    Storage::disk('local')->put('og/'.OgRenderer::generation()."/entry/{$entry->id}-".BuildEntryOgData::entryTimestamp($entry).'.png', 'fake-png-bytes');

    $this->get("/og/entry/{$entry->id}.png")->assertNotFound();

    $image = $this->get('/2026/06/15/hidden-card')->inertiaProps('og.image');

    $this->get($image)->assertOk()->assertHeader('content-type', 'image/png');
    $this->get(str_replace('signature=', 'signature=0', $image))->assertNotFound();
})->with([
    'unlisted' => [EntryStatus::Unlisted],
    'private' => [EntryStatus::Private],
]);

it('serves a published entry\'s card at its plain url', function () {
    Storage::fake('local');

    $note = Note::factory()->create(['slug' => 'open-card', 'occurred_at' => '2026-06-15 09:00:00']);
    $entry = TimelineEntry::query()->where('entry_id', $note->id)->sole();
    Storage::disk('local')->put('og/'.OgRenderer::generation()."/entry/{$entry->id}-".BuildEntryOgData::entryTimestamp($entry).'.png', 'fake-png-bytes');

    $image = $this->get('/2026/06/15/open-card')->inertiaProps('og.image');

    expect($image)->not->toContain('signature=');
    $this->get("/og/entry/{$entry->id}.png")->assertOk();
});

it('serves the preview card for a hyphenated dataset key', function () {
    Storage::fake('local');
    Storage::disk('local')->put('og/'.OgRenderer::generation().'/preview/'.md5('this-week-with').'.png', 'fake-png-bytes');

    $this->get('/og/preview/this-week-with.png')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('404s the preview card for a retired dataset key', function () {
    $this->get('/og/preview/podcast.png')->assertNotFound();
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
