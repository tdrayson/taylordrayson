<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\Note;
use Illuminate\Support\Carbon;
use Statamic\Facades\Entry;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

/**
 * Build a zero-padded month URL (required by route: \d{2} constraint).
 */
function monthUrl(int $year, int $month): string
{
    return sprintf('/%d/%02d', $year, $month);
}

/**
 * Build a zero-padded day URL (required by route: \d{2} constraint).
 */
function dayUrl(Carbon $date): string
{
    return $date->format('/Y/m/d');
}

// ---------------------------------------------------------------------------
// Month view: Statamic content included; Eloquent Article/Note excluded
// ---------------------------------------------------------------------------

it('includes a Statamic article in the month entriesCount', function () {
    $ref = now()->subMonths(2);
    $year = (int) $ref->format('Y');
    $month = (int) $ref->format('n');
    $date = $ref->copy()->startOfMonth()->toDateString();

    Entry::make()->collection('articles')->slug('month-article-merge')
        ->date($date)
        ->data(['title' => 'Month Article', 'excerpt' => 'In month'])
        ->save();

    get(monthUrl($year, $month))
        ->assertInertia(fn ($page) => $page
            ->component('Month')
            ->where('entriesCount', fn ($count) => $count >= 1)
        );
});

it('includes a Statamic article type indicator in monthDays for its day', function () {
    $ref = now()->subMonths(2);
    $year = (int) $ref->format('Y');
    $month = (int) $ref->format('n');
    $dayOfMonth = 5;
    $date = $ref->copy()->setDay($dayOfMonth)->toDateString();

    Entry::make()->collection('articles')->slug('month-day-indicator-article')
        ->date($date)
        ->data(['title' => 'Day Indicator Article', 'excerpt' => 'Check indicator'])
        ->save();

    get(monthUrl($year, $month))
        ->assertInertia(function ($page) use ($dayOfMonth) {
            $days = $page->toArray()['props']['days'];

            // The day of month should appear in the days map with 'article' in types.
            expect($days)->toHaveKey((string) $dayOfMonth);
            expect($days[(string) $dayOfMonth]['types'])->toContain('article');
        });
});

it('does not duplicate a migrated Eloquent Article in the month days map', function () {
    $ref = now()->subMonths(2);
    $year = (int) $ref->format('Y');
    $month = (int) $ref->format('n');
    $dayOfMonth = 10;
    $date = $ref->copy()->setDay($dayOfMonth)->toDateString();

    // Eloquent Article (excluded morph type).
    Article::factory()->create([
        'title' => 'Legacy Month Article',
        'occurred_at' => $date.' 09:00:00',
        'draft' => false,
    ]);

    // Statamic twin for the same day.
    Entry::make()->collection('articles')->slug('month-no-dup-article')
        ->date($date)
        ->data(['title' => 'Statamic Month Article', 'excerpt' => 'No dup'])
        ->save();

    get(monthUrl($year, $month))
        ->assertInertia(function ($page) use ($dayOfMonth) {
            $days = $page->toArray()['props']['days'];

            expect($days)->toHaveKey((string) $dayOfMonth);

            // Only one article type in that day's indicator row — no duplicate.
            $articleCount = collect($days[(string) $dayOfMonth]['types'])
                ->filter(fn ($t) => $t === 'article')
                ->count();

            expect($articleCount)->toBe(1, "Expected 1 article indicator, got {$articleCount}");
        });
});

it('does not show a draft Statamic article in the month view', function () {
    // Use a far-past year/month with no committed Statamic content to isolate the count.
    $year = 2020;
    $month = 3;
    $date = '2020-03-15';

    Entry::make()->collection('articles')->slug('draft-month-article')
        ->date($date)
        ->data(['title' => 'Draft Month Article', 'excerpt' => 'Not visible'])
        ->published(false)
        ->save();

    get(monthUrl($year, $month))
        ->assertInertia(fn ($page) => $page
            ->component('Month')
            ->where('entriesCount', 0)
        );
});

// ---------------------------------------------------------------------------
// Day view: Statamic content included; Eloquent Article/Note excluded
// ---------------------------------------------------------------------------

it('includes a Statamic article card on the day view', function () {
    $date = now()->subDays(14)->startOfDay();

    Entry::make()->collection('articles')->slug('day-view-article')
        ->date($date->toDateString())
        ->data(['title' => 'Day View Article', 'excerpt' => 'Show on day'])
        ->save();

    get(dayUrl($date))
        ->assertInertia(function ($page) {
            $titles = collect($page->toArray()['props']['items'])->pluck('title');
            expect($titles)->toContain('Day View Article');
        });
});

it('includes a Statamic note card on the day view', function () {
    $date = now()->subDays(15)->startOfDay();

    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A day view note entry']]],
    ];

    Entry::make()->collection('notes')->slug('day-view-note')
        ->date($date->toDateString())
        ->data(['content' => $bardContent])
        ->save();

    get(dayUrl($date))
        ->assertInertia(function ($page) {
            $titles = collect($page->toArray()['props']['items'])->pluck('title');
            $hasNote = $titles->first(fn ($t) => str_contains((string) $t, 'A day view note entry'));
            expect($hasNote)->not->toBeNull();
        });
});

it('does not duplicate an Eloquent Article on the day view', function () {
    $date = now()->subDays(16)->startOfDay();

    // Eloquent Article — creates a timeline_entries morph row (excluded).
    Article::factory()->create([
        'title' => 'Legacy Day Article',
        'occurred_at' => $date->copy()->setTime(11, 0),
        'draft' => false,
    ]);

    // Statamic twin.
    Entry::make()->collection('articles')->slug('day-no-dup-article')
        ->date($date->toDateString())
        ->data(['title' => 'Statamic Day Article', 'excerpt' => 'No dup day'])
        ->save();

    get(dayUrl($date))
        ->assertInertia(function ($page) {
            $items = collect($page->toArray()['props']['items']);

            $articleCount = $items->filter(fn ($item) => ($item['iconKey'] ?? '') === 'article')->count();
            expect($articleCount)->toBe(1, "Expected 1 article card on day view, got {$articleCount}");

            $titles = $items->pluck('title');
            expect($titles)->toContain('Statamic Day Article');
            expect($titles)->not->toContain('Legacy Day Article');
        });
});

it('does not duplicate an Eloquent Note on the day view', function () {
    $date = now()->subDays(17)->startOfDay();

    // Eloquent Note — excluded morph type.
    Note::factory()->create([
        'occurred_at' => $date->copy()->setTime(9, 0),
        'content' => 'Legacy day note content',
    ]);

    // Statamic note.
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Statamic day note content']]],
    ];

    Entry::make()->collection('notes')->slug('day-no-dup-note')
        ->date($date->toDateString())
        ->data(['content' => $bardContent])
        ->save();

    get(dayUrl($date))
        ->assertInertia(function ($page) {
            $items = collect($page->toArray()['props']['items']);

            $noteCount = $items->filter(fn ($item) => ($item['iconKey'] ?? '') === 'note')->count();
            expect($noteCount)->toBe(1, "Expected 1 note card on day view, got {$noteCount}");
        });
});

it('does not show a draft Statamic article on the day view', function () {
    $date = now()->subDays(18)->startOfDay();

    // An activity to ensure the day is reachable.
    Activity::factory()->create([
        'name' => 'Draft Day Activity',
        'occurred_at' => $date->copy()->setTime(8, 0),
    ]);

    Entry::make()->collection('articles')->slug('draft-day-article')
        ->date($date->toDateString())
        ->data(['title' => 'Draft Day Article', 'excerpt' => 'Should not appear'])
        ->published(false)
        ->save();

    get(dayUrl($date))
        ->assertInertia(function ($page) {
            $titles = collect($page->toArray()['props']['items'])->pluck('title');
            expect($titles)->not->toContain('Draft Day Article');
        });
});

it('merges Statamic and Eloquent items on the day view sorted earliest first', function () {
    $date = now()->subDays(19)->startOfDay();

    // Activity at 10:00 — should appear AFTER the midnight article when sorted earliest first.
    Activity::factory()->create([
        'name' => 'Day Activity',
        'occurred_at' => $date->copy()->setTime(10, 0),
    ]);

    // Statamic article — date-only, treated as midnight (00:00).
    Entry::make()->collection('articles')->slug('day-order-article')
        ->date($date->toDateString())
        ->data(['title' => 'Midnight Article', 'excerpt' => 'Date only'])
        ->save();

    get(dayUrl($date))
        ->assertInertia(function ($page) {
            $titles = collect($page->toArray()['props']['items'])->pluck('title');

            // Day view is earliest-first: midnight article before 10:00 activity.
            expect($titles->first())->toBe('Midnight Article');
            expect($titles->last())->toBe('Day Activity');
        });
});
