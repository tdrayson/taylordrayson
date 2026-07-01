<?php

use App\Models\Checkin;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Entry;

/**
 * The feed item's <category> is the card type, so filtering can be asserted
 * purely by which type categories appear in the rendered feed.
 *
 * Notes and articles are now sourced exclusively from Statamic (ContentRepository);
 * Eloquent Note/Article rows are excluded from the TimelineEntry feed query.
 */
function category(string $type): string
{
    return "<category>{$type}</category>";
}

beforeEach(function () {
    $this->preContent = snapshotContentFiles();

    // Statamic entries replace the legacy Eloquent Note/Article rows.
    Entry::make()->collection('notes')->slug('feed-builder-note')
        ->date(now()->subDay()->toDateString())
        ->data(['content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Feed builder note']]]]])
        ->save();

    Entry::make()->collection('articles')->slug('feed-builder-article')
        ->date(now()->subDays(2)->toDateString())
        ->data(['title' => 'Feed Builder Article', 'excerpt' => 'desc'])
        ->save();

    Checkin::factory()->create(['occurred_at' => now()->subDays(3)]);
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

it('returns every type when no filter is given', function () {
    $response = $this->get('/feed/rss')->assertSuccessful();

    $response->assertSee(category('note'), false)
        ->assertSee(category('article'), false)
        ->assertSee(category('checkin'), false);
});

it('filters to a single type via the types parameter', function () {
    $response = $this->get('/feed/rss?types=note')->assertSuccessful();

    $response->assertSee(category('note'), false)
        ->assertDontSee(category('article'), false)
        ->assertDontSee(category('checkin'), false);
});

it('combines several types via the types parameter', function () {
    $response = $this->get('/feed/rss?types=note,checkin')->assertSuccessful();

    $response->assertSee(category('note'), false)
        ->assertSee(category('checkin'), false)
        ->assertDontSee(category('article'), false);
});

it('resolves a named preset via the filter parameter', function () {
    $response = $this->get('/feed/rss?filter=writing')->assertSuccessful();

    $response->assertSee(category('note'), false)
        ->assertSee(category('article'), false)
        ->assertDontSee(category('checkin'), false);
});

it('ignores unknown types but keeps the valid ones', function () {
    $response = $this->get('/feed/rss?types=note,unicorn')->assertSuccessful();

    $response->assertSee(category('note'), false)
        ->assertDontSee(category('article'), false)
        ->assertDontSee(category('checkin'), false);
});

it('falls back to everything when no valid type remains', function () {
    $response = $this->get('/feed/rss?types=unicorn')->assertSuccessful();

    $response->assertSee(category('note'), false)
        ->assertSee(category('article'), false)
        ->assertSee(category('checkin'), false);
});

it('applies the same filter to the json feed', function () {
    $response = $this->get('/feed/json?filter=writing')->assertSuccessful();

    $response->assertSee('"note"', false)
        ->assertSee('"article"', false)
        ->assertDontSee('"checkin"', false);
});

it('renders the subscribe page with every type and preset', function () {
    $this->get('/feeds')
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Feeds')
            ->has('types', 13)
            ->has('presets', 6)
            ->where('presets.0.key', 'curated')
        );
});
