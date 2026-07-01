<?php

use App\Models\Article;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Note;
use Statamic\Facades\Entry;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

// ---------------------------------------------------------------------------
// Statamic articles appear in the feed
// ---------------------------------------------------------------------------

it('includes a statamic article as a feed item with the correct link', function () {
    Entry::make()->collection('articles')->slug('my-statamic-post')
        ->date('2024-03-15')->data(['title' => 'My Statamic Post', 'excerpt' => 'A great excerpt'])->save();

    $this->get('/feed/rss')
        ->assertOk()
        ->assertSee('My Statamic Post')
        ->assertSee('/2024/03/15/my-statamic-post');
});

it('includes a statamic note as a feed item', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'This is a short note body']]],
    ];

    Entry::make()->collection('notes')->slug('my-statamic-note')
        ->date('2024-04-10')->data(['content' => $bardContent])->save();

    $this->get('/feed/rss')
        ->assertOk()
        ->assertSee('/2024/04/10/my-statamic-note');
});

it('uses excerpt as summary for statamic articles', function () {
    Entry::make()->collection('articles')->slug('excerpt-test')
        ->date('2024-05-01')->data(['title' => 'Excerpt Article', 'excerpt' => 'The excerpt text'])->save();

    $this->get('/feed/rss')
        ->assertOk()
        ->assertSee('The excerpt text');
});

// ---------------------------------------------------------------------------
// No duplication: migrated Eloquent Article/Note rows do NOT appear alongside
// their Statamic counterpart
// ---------------------------------------------------------------------------

it('a migrated eloquent article title does not appear from the eloquent path', function () {
    // Eloquent row (migrated, legacy): should be excluded from the Eloquent feed
    // because Article/Note now come exclusively from Statamic.
    $uniqueTitle = 'Unique Migrated Article Title '.uniqid();
    Article::factory()->create([
        'title' => $uniqueTitle,
        'occurred_at' => now()->subDays(5),
    ]);

    $this->get('/feed/rss')
        ->assertOk()
        // The Eloquent article's title must NOT appear: Statamic is the sole
        // source for articles, so the migrated Eloquent row is suppressed.
        ->assertDontSee($uniqueTitle);
});

it('types=article shows statamic articles but not their eloquent duplicates', function () {
    $uniqueTitle = 'Only From Statamic '.uniqid();
    $eloquentTitle = 'Should Be Hidden '.uniqid();

    // Eloquent Article (migrated row) — should be excluded.
    Article::factory()->create([
        'title' => $eloquentTitle,
        'occurred_at' => now()->subDays(3),
    ]);

    // Statamic article — should appear.
    Entry::make()->collection('articles')->slug('dedup-statamic')
        ->date('2024-06-01')->data(['title' => $uniqueTitle])->save();

    $this->get('/feed/rss?types=article')
        ->assertOk()
        ->assertSee($uniqueTitle)
        ->assertDontSee($eloquentTitle);
});

it('the feed category is article for statamic articles', function () {
    Entry::make()->collection('articles')->slug('category-test')
        ->date('2024-06-01')->data(['title' => 'Category Article'])->save();

    $this->get('/feed/rss')
        ->assertOk()
        ->assertSee('<category>article</category>', false);
});

it('the feed category is note for statamic notes', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Note category test']]],
    ];

    Entry::make()->collection('notes')->slug('note-category-test')
        ->date('2024-06-02')->data(['content' => $bardContent])->save();

    $this->get('/feed/rss')
        ->assertOk()
        ->assertSee('<category>note</category>', false);
});

// ---------------------------------------------------------------------------
// Selection filtering
// ---------------------------------------------------------------------------

it('types=flight excludes statamic articles and notes', function () {
    Entry::make()->collection('articles')->slug('flight-filter-article')
        ->date('2024-03-01')->data(['title' => 'Should Be Excluded'])->save();

    Flight::factory()->create(['occurred_at' => now()->subDay()]);

    $this->get('/feed/rss?types=flight')
        ->assertOk()
        ->assertSee('<category>flight</category>', false)
        ->assertDontSee('Should Be Excluded');
});

it('types=article includes statamic articles and excludes non-content types', function () {
    Entry::make()->collection('articles')->slug('article-filter-test')
        ->date('2024-03-01')->data(['title' => 'Filtered Statamic Article'])->save();

    Checkin::factory()->create(['occurred_at' => now()->subDay()]);

    $this->get('/feed/rss?types=article')
        ->assertOk()
        ->assertSee('Filtered Statamic Article')
        ->assertDontSee('<category>checkin</category>', false);
});

it('types=note includes statamic notes and excludes non-content types', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Filtered Note Body Text']]],
    ];

    Entry::make()->collection('notes')->slug('note-filter-test')
        ->date('2024-03-01')->data(['content' => $bardContent])->save();

    Checkin::factory()->create(['occurred_at' => now()->subDay()]);

    $this->get('/feed/rss?types=note')
        ->assertOk()
        ->assertSee('<category>note</category>', false)
        ->assertDontSee('<category>checkin</category>', false);
});

it('filter=writing preset includes statamic articles and notes', function () {
    Entry::make()->collection('articles')->slug('writing-preset-article')
        ->date('2024-03-01')->data(['title' => 'Writing Preset Article'])->save();

    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Writing preset note text']]],
    ];
    Entry::make()->collection('notes')->slug('writing-preset-note')
        ->date('2024-03-02')->data(['content' => $bardContent])->save();

    Checkin::factory()->create(['occurred_at' => now()->subDay()]);

    $this->get('/feed/rss?filter=writing')
        ->assertOk()
        ->assertSee('<category>article</category>', false)
        ->assertSee('<category>note</category>', false)
        ->assertDontSee('<category>checkin</category>', false);
});

// ---------------------------------------------------------------------------
// Draft content exclusion
// ---------------------------------------------------------------------------

it('excludes draft statamic articles from the feed', function () {
    Entry::make()->collection('articles')->slug('draft-article')
        ->date('2024-03-01')->data(['title' => 'Draft Article'])->published(false)->save();

    $this->get('/feed/rss')
        ->assertOk()
        ->assertDontSee('Draft Article');
});

// ---------------------------------------------------------------------------
// Author metadata
// ---------------------------------------------------------------------------

it('statamic feed items include the correct author name', function () {
    Entry::make()->collection('articles')->slug('author-test')
        ->date('2024-03-01')->data(['title' => 'Author Test Article'])->save();

    $this->get('/feed/rss')
        ->assertOk()
        ->assertSee(config('feed.author_name'));
});
