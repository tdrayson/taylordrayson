<?php

use App\Models\Note;
use App\Models\Tag;
use App\Models\Trip;
use Inertia\Inertia;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

/**
 * The deferred `interactions` prop as a page's second request would fetch it.
 *
 * @return array<string, mixed>
 */
function deferredInteractions(string $url, string $component): array
{
    return get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => 'interactions',
    ])->assertOk()->json('props.interactions') ?? [];
}

/**
 * Every view that draws timeline cards sends the counts those cards' reaction
 * bars are built from. Without the prop the card renders, the bar does not, and
 * the page silently loses the whole interaction surface.
 */
it('sends reaction counts to every page of feed cards', function (callable $url, string $component) {
    $note = Note::factory()->create(['occurred_at' => now()]);

    postJson("/reactions/note/{$note->id}", ['type' => 'love', 'reactor' => fake()->uuid()])->assertSuccessful();

    $tag = Tag::create(['name' => 'Squash', 'slug' => 'squash']);
    $note->tags()->attach($tag);

    Trip::factory()->create([
        'slug' => 'london',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'timezone' => 'Europe/London',
    ]);

    $interactions = deferredInteractions($url(), $component);

    expect($interactions)->toHaveKey("note:{$note->id}");
    expect(collect($interactions["note:{$note->id}"]['reactions'])->firstWhere('key', 'love')['count'])->toBe(1);
})->with([
    'timeline' => [fn (): string => '/', 'Timeline'],
    'archive' => [fn (): string => '/notes', 'Archive'],
    'day' => [fn (): string => now()->format('/Y/m/d'), 'Day'],
    'month' => [fn (): string => now()->format('/Y/m'), 'Month'],
    'year' => [fn (): string => now()->format('/Y'), 'Year'],
    'on this day' => [fn (): string => '/on-this-day', 'OnThisDay'],
    'tag' => [fn (): string => '/tags/squash', 'Tag'],
    'trip' => [fn (): string => '/trips/london', 'Trip'],
    'search' => [fn (): string => '/search?'.http_build_query([
        'filter' => json_encode([[
            'type' => 'note',
            'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => 'published']],
        ]]),
    ]), 'Search'],
]);
