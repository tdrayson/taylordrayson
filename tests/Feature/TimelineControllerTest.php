<?php

use App\Models\Activity;
use App\Models\Film;
use App\Models\Flight;
use App\Models\Food;
use App\Models\Note;
use App\Models\ThisWeekWith;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('returns 200 for the homepage', function () {
    get('/')->assertOk();
});

it('renders the timeline page via Inertia', function () {
    get('/')->assertInertia(fn ($page) => $page->component('Timeline'));
});

it('shares the real This Week With episode count', function () {
    ThisWeekWith::factory()->count(3)->create();

    get('/')->assertInertia(fn ($page) => $page->where('thisWeekWithEpisodes', 3));
});

it('groups timeline entries by day, newest day first', function () {
    Activity::factory()->create(['name' => 'Oldest Activity', 'occurred_at' => now()->subDays(3)]);
    Activity::factory()->create(['name' => 'Middle Activity', 'occurred_at' => now()->subDays(2)]);
    Activity::factory()->create(['name' => 'Newest Activity', 'occurred_at' => now()->subDay()]);

    get('/')->assertInertia(fn ($page) => $page
        ->component('Timeline')
        ->has('groups', 3)
        ->where('groups.0.items.0.title', 'Newest Activity')
        ->where('groups.2.items.0.title', 'Oldest Activity')
    );
});

it('orders entries within a day latest first', function () {
    Activity::factory()->create(['name' => 'Morning Run', 'occurred_at' => now()->setTime(7, 0)]);
    Activity::factory()->create(['name' => 'Evening Run', 'occurred_at' => now()->setTime(20, 0)]);

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.title', 'Evening Run')
        ->where('groups.0.items.1.title', 'Morning Run')
    );
});

it('exposes the card type for each entry', function () {
    Food::factory()->create(['occurred_at' => now()]);

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.iconKey', 'food')
        ->where('groups.0.items.0.url', fn ($url) => str_contains($url, '/'))
    );
});

it('pages 50 entries on a time cursor, splitting a day across pages', function () {
    foreach (range(1, 60) as $minute) {
        Activity::factory()->create(['occurred_at' => now()->subDay()->setTime(12, $minute)]);
    }
    Activity::factory()->create(['occurred_at' => now()->subDays(2)]);

    $first = get('/')->assertInertia(fn ($page) => $page
        ->where('newerUrl', null)
        ->has('groups', 1)
        ->has('groups.0.items', 50)
    );

    $older = $first->viewData('page')['props']['olderUrl'];

    expect($older)->toMatch('#^/\?before=\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$#');

    $second = get($older)->assertInertia(fn ($page) => $page
        ->has('groups', 2)
        ->has('groups.0.items', 10)
        ->where('olderUrl', null)
        ->where('newerUrl', fn ($url) => str_starts_with((string) $url, '/?after='))
    );

    // Walking back up lands on the same 50 as the front.
    get($second->viewData('page')['props']['newerUrl'])->assertInertia(fn ($page) => $page
        ->has('groups.0.items', 50)
        ->where('newerUrl', null)
    );
});

it('keeps entries sharing an instant on one page', function () {
    Activity::factory()->count(49)->create(['occurred_at' => now()->subDay()->setTime(18, 0)]);
    Activity::factory()->count(3)->create(['occurred_at' => now()->subDay()->setTime(9, 0)]);

    get('/')->assertInertia(fn ($page) => $page
        ->has('groups.0.items', 52)
        ->where('olderUrl', null)
    );
});

it('still reads a bare date cursor from older links', function () {
    Activity::factory()->create(['occurred_at' => '2026-03-02 10:00:00']);
    Activity::factory()->create(['occurred_at' => '2026-03-01 10:00:00']);

    get('/?before=2026-03-02')->assertInertia(fn ($page) => $page
        ->has('groups', 1)
        ->where('groups.0.date', '2026-03-01')
    );
});

it('ignores a cursor that is not a date', function () {
    Activity::factory()->create(['occurred_at' => now()->subDay()]);

    foreach (['garbage', '2026-13-99', '2026-01-01T25:00:00', '../etc/passwd'] as $cursor) {
        get('/?before='.urlencode($cursor))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('groups', 1));
    }
});

it('offers every year the timeline holds something in', function () {
    Activity::factory()->create(['occurred_at' => '2024-06-01 09:00:00']);
    Activity::factory()->create(['occurred_at' => '2022-06-01 09:00:00']);

    get('/')->assertInertia(fn ($page) => $page
        ->where('years.0.year', 2024)
        ->where('years.0.href', '/2024')
        ->where('years.1.year', 2022)
    );
});

it('renders different card types together', function () {
    Activity::factory()->create(['name' => 'Morning Park Run', 'occurred_at' => now()->subHour()]);
    Flight::factory()->create(['origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'occurred_at' => now()->subHours(2)]);
    Film::factory()->create(['title' => 'The Shawshank Redemption', 'occurred_at' => now()->subHours(3)]);
    Note::factory()->create(['content' => 'A unique test note for verification', 'occurred_at' => now()->subHours(4)]);

    get('/')->assertInertia(function ($page) {
        $titles = collect($page->toArray()['props']['groups'])
            ->flatMap(fn ($group) => collect($group['items'])->pluck('title'));

        expect($titles)
            ->toContain('Morning Park Run')
            ->toContain('LHR → JFK')
            ->toContain('The Shawshank Redemption');
    });
});

it('shows day grouping headers', function () {
    Activity::factory()->create(['occurred_at' => now()->subDay()]);
    Activity::factory()->create(['occurred_at' => now()->subDays(3)]);

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.label', now()->subDay()->format('l j F Y'))
        ->where('groups.1.label', now()->subDays(3)->format('l j F Y'))
    );
});

it('ships the whole note document as the card body, paragraphs intact', function () {
    Note::factory()->create([
        'content' => "Long thought about grinders.\n\nSecond paragraph of the same note.",
        'occurred_at' => now()->subHour(),
    ]);

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.body', fn ($body) => count($body) === 2
            && PortableText::plainText($body) === 'Long thought about grinders. Second paragraph of the same note.')
        ->where('groups.0.items.0.iconKey', 'note'));
});

/**
 * The day page used to send a fixed Apple Health summary with every request, so
 * every day in the archive reported the same 137 kcal, 32 minutes, 9 hours and
 * 11,240 steps regardless of what happened. Nothing marked it as invented, and
 * it read exactly like the real entry-derived stats beside it.
 */
it('does not put invented health figures on a day page', function () {
    $activity = Activity::factory()->create(['occurred_at' => '2026-03-15 07:30:00', 'type' => 'run', 'distance' => 5000, 'duration' => 1800]);

    $this->get('/2026/03/15')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Day')
        ->missing('rings')
        ->missing('steps')
        // The stats that are genuinely derived from the day's entries stay.
        ->has('stats')
        ->has('items', 1)
    );

    expect($activity->fresh())->not->toBeNull();
});
