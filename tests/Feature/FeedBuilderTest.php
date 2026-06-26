<?php

use App\Models\Article;
use App\Models\Checkin;
use App\Models\Note;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The feed item's <category> is the card type, so filtering can be asserted
 * purely by which type categories appear in the rendered feed.
 */
function category(string $type): string
{
    return "<category>{$type}</category>";
}

beforeEach(function () {
    Note::factory()->create(['occurred_at' => now()->subDay()]);
    Article::factory()->create(['occurred_at' => now()->subDays(2)]);
    Checkin::factory()->create(['occurred_at' => now()->subDays(3)]);
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
        ->assertInertia(fn (Assert $page) => $page
            ->component('Feeds')
            ->has('types', 13)
            ->has('presets', 6)
            ->where('presets.0.key', 'curated')
        );
});
