<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\Note;
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
// No duplicate: Eloquent Article morph rows are excluded when Statamic twin exists
// ---------------------------------------------------------------------------

it('shows an Eloquent-era Article only once (from Statamic, not doubled)', function () {
    $date = now()->subDays(6)->startOfDay();

    // Eloquent Article — creates a timeline_entries morph row via observer.
    Article::factory()->create([
        'title' => 'Legacy Article Title',
        'occurred_at' => $date->copy()->setTime(10, 0),
        'draft' => false,
    ]);

    // Statamic twin for the same conceptual post.
    Entry::make()->collection('articles')->slug('statamic-twin')
        ->date($date->toDateString())
        ->data(['title' => 'Statamic Twin Article', 'excerpt' => 'Twin'])
        ->save();

    get('/')->assertInertia(function ($page) use ($date) {
        $groups = collect($page->toArray()['props']['groups']);

        $group = $groups->first(fn ($g) => $g['date'] === $date->toDateString());

        expect($group)->not->toBeNull("No group found for date {$date->toDateString()}");

        $types = collect($group['items'])->pluck('iconKey');

        // The Statamic twin should appear.
        $articleCount = $types->filter(fn ($t) => $t === 'article')->count();

        // Only the Statamic twin appears (Eloquent morph excluded) — not doubled.
        // Eloquent Article morph rows are excluded entirely; Statamic provides the one entry.
        expect($articleCount)->toBe(1, "Expected exactly 1 article card, got {$articleCount}");

        // The Statamic title should be present.
        $titles = collect($group['items'])->pluck('title');
        expect($titles)->toContain('Statamic Twin Article');

        // The legacy Eloquent title must NOT appear.
        expect($titles)->not->toContain('Legacy Article Title');
    });
});

// ---------------------------------------------------------------------------
// No duplicate: Eloquent Note morph rows are excluded
// ---------------------------------------------------------------------------

it('shows a Statamic note and excludes any Eloquent Note morph rows on the same day', function () {
    $date = now()->subDays(7)->startOfDay();

    // Eloquent Note — creates a timeline_entries morph row.
    Note::factory()->create([
        'occurred_at' => $date->copy()->setTime(9, 0),
        'content' => 'Legacy note content for test',
    ]);

    // Statamic note.
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Statamic note text here']]],
    ];

    Entry::make()->collection('notes')->slug('statamic-note-merge')
        ->date($date->toDateString())
        ->data(['content' => $bardContent])
        ->save();

    get('/')->assertInertia(function ($page) use ($date) {
        $groups = collect($page->toArray()['props']['groups']);

        $group = $groups->first(fn ($g) => $g['date'] === $date->toDateString());

        expect($group)->not->toBeNull("No group found for date {$date->toDateString()}");

        $noteCount = collect($group['items'])->pluck('iconKey')->filter(fn ($t) => $t === 'note')->count();

        // Only the Statamic note appears; Eloquent morph is excluded.
        expect($noteCount)->toBe(1, "Expected exactly 1 note card, got {$noteCount}");

        $titles = collect($group['items'])->pluck('title');
        expect($titles->first())->toContain('Statamic note text here');
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

    // Draft article — must not appear.
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

    // Statamic article — date-only, treated as midnight (00:00) by Statamic.
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

    // A Statamic article on a day with NO Eloquent entries — day 10.
    $statamicDay = now()->subDays(10)->toDateString();

    Entry::make()->collection('articles')->slug('statamic-only-day')
        ->date($statamicDay)
        ->data(['title' => 'Statamic Only', 'excerpt' => 'No Eloquent entry this day'])
        ->save();

    // Page 1 shows 10 days — the 9 Eloquent days plus the Statamic-only day.
    get('/')->assertInertia(function ($page) use ($statamicDay) {
        $groups = collect($page->toArray()['props']['groups']);

        expect($groups)->toHaveCount(10);

        $dates = $groups->pluck('date');
        expect($dates)->toContain($statamicDay);
    });
});
