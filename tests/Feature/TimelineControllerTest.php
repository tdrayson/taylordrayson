<?php

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('returns 200 for the homepage', function () {
    get('/')->assertOk();
});

it('renders the timeline page via Inertia', function () {
    get('/')->assertInertia(fn ($page) => $page->component('Timeline'));
});

it('shares the real This Week With episode count', function () {
    Podcast::factory()->count(3)->create();

    get('/')->assertInertia(fn ($page) => $page->where('podcastEpisodes', 3));
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
    Calorie::factory()->create(['occurred_at' => now()]);

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.iconKey', 'calorie')
        ->where('groups.0.items.0.url', fn ($url) => str_contains($url, '/'))
    );
});

it('paginates by day', function () {
    foreach (range(1, 12) as $offset) {
        Activity::factory()->create(['occurred_at' => now()->subDays($offset)]);
    }

    get('/')->assertInertia(fn ($page) => $page
        ->where('currentPage', 1)
        ->where('lastPage', 2)
        ->has('groups', 10)
    );

    get('/?page=2')->assertInertia(fn ($page) => $page
        ->where('currentPage', 2)
        ->has('groups', 2)
    );
});

it('renders different card types together', function () {
    Activity::factory()->create(['name' => 'Morning Park Run', 'occurred_at' => now()->subHour()]);
    Flight::factory()->create(['origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'occurred_at' => now()->subHours(2)]);
    Media::factory()->create(['title' => 'The Shawshank Redemption', 'type' => 'film', 'occurred_at' => now()->subHours(3)]);
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
