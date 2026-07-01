<?php

use App\Models\Activity;
use Statamic\Facades\Entry;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

// ---------------------------------------------------------------------------
// Core merge: Statamic articles appear alongside Eloquent cards
// ---------------------------------------------------------------------------

it('includes a Statamic article on the same day as an Eloquent activity', function () {
    $date = now()->subDays(5)->startOfDay();

    Activity::factory()->create([
        'name' => 'Morning Run',
        'occurred_at' => $date->copy()->setTime(7, 0),
    ]);

    Entry::make()->collection('articles')->slug('test-article-merge')
        ->date($date->toDateString())
        ->data(['title' => 'My Statamic Article', 'excerpt' => 'Statamic excerpt'])
        ->save();

    get('/')->assertInertia(function ($page) use ($date) {
        $groups = collect($page->toArray()['props']['groups']);

        // Find the group for our date.
        $group = $groups->first(fn ($g) => $g['date'] === $date->toDateString());

        expect($group)->not->toBeNull("No group found for date {$date->toDateString()}");

        $titles = collect($group['items'])->pluck('title');

        expect($titles)->toContain('Morning Run');
        expect($titles)->toContain('My Statamic Article');
    });
});

// ---------------------------------------------------------------------------
// Draft Statamic content must NOT appear
// ---------------------------------------------------------------------------

it('does not show a draft Statamic article on the timeline', function () {
    $date = now()->subDays(4)->startOfDay();

    // A published activity to ensure the day group exists.
    Activity::factory()->create([
        'name' => 'Draft Day Run',
        'occurred_at' => $date->copy()->setTime(8, 0),
    ]);

    // Draft article -- must not appear.
    Entry::make()->collection('articles')->slug('draft-article-merge')
        ->date($date->toDateString())
        ->data(['title' => 'Draft Article', 'excerpt' => 'Should not appear'])
        ->published(false)
        ->save();

    get('/')->assertInertia(function ($page) use ($date) {
        $groups = collect($page->toArray()['props']['groups']);

        $group = $groups->first(fn ($g) => $g['date'] === $date->toDateString());

        expect($group)->not->toBeNull();

        $titles = collect($group['items'])->pluck('title');
        expect($titles)->not->toContain('Draft Article');
    });
});

// ---------------------------------------------------------------------------
// Ordering: items within a day group are sorted newest first
// ---------------------------------------------------------------------------

it('orders merged cards within a day group newest first', function () {
    $date = now()->subDays(3)->startOfDay();

    // Activity at 06:00.
    Activity::factory()->create([
        'name' => 'Early Run',
        'occurred_at' => $date->copy()->setTime(6, 0),
    ]);

    // Statamic article -- date-only, treated as midnight (00:00) by Statamic.
    // The activity at 06:00 should sort above the article at 00:00.
    Entry::make()->collection('articles')->slug('ordering-test-article')
        ->date($date->toDateString())
        ->data(['title' => 'Late Night Post', 'excerpt' => 'Posted at midnight'])
        ->save();

    get('/')->assertInertia(function ($page) use ($date) {
        $groups = collect($page->toArray()['props']['groups']);

        $group = $groups->first(fn ($g) => $g['date'] === $date->toDateString());

        expect($group)->not->toBeNull();
        expect(collect($group['items'])->pluck('title')->first())->toBe('Early Run');
    });
});

// ---------------------------------------------------------------------------
// Pagination: Statamic-only days appear in the paginated date list
// ---------------------------------------------------------------------------

it('includes a Statamic-only day in the paginated date set', function () {
    // Create 9 Eloquent activities on 9 distinct days.
    foreach (range(1, 9) as $i) {
        Activity::factory()->create(['occurred_at' => now()->subDays($i)->setTime(10, 0)]);
    }

    // A Statamic article on a day with NO Eloquent entries -- day 10.
    $statamicDay = now()->subDays(10)->toDateString();

    Entry::make()->collection('articles')->slug('statamic-only-day')
        ->date($statamicDay)
        ->data(['title' => 'Statamic Only', 'excerpt' => 'No Eloquent entry this day'])
        ->save();

    // Page 1 shows 10 days -- the 9 Eloquent days plus the Statamic-only day.
    get('/')->assertInertia(function ($page) use ($statamicDay) {
        $groups = collect($page->toArray()['props']['groups']);

        expect($groups)->toHaveCount(10);

        $dates = $groups->pluck('date');
        expect($dates)->toContain($statamicDay);
    });
});
