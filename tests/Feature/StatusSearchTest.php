<?php

use App\Enums\EntryStatus;
use App\Mcp\Tools\Entry;
use App\Mcp\Tools\SearchEntries;
use App\Mcp\Tools\Timeline;
use App\Models\Article;
use App\Models\User;
use App\Queries\MentionSearch;
use App\Search\SearchSchema;
use Inertia\Testing\AssertableInertia as Assert;

function statusSearchUrl(array $filter): string
{
    return '/search?'.http_build_query(['filter' => json_encode($filter)]);
}

beforeEach(function (): void {
    foreach (EntryStatus::cases() as $status) {
        Article::factory()->create([
            'title' => "Kettle {$status->value}",
            'slug' => "kettle-{$status->value}",
            'occurred_at' => '2026-06-15 09:00:00',
            'status' => $status,
            'password' => $status === EntryStatus::Private ? 'hunter2' : null,
        ]);
    }
});

it('finds published entries for a guest and everything but drafts for the owner', function () {
    $filter = [['type' => 'article', 'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Kettle']]]];

    $this->get(statusSearchUrl($filter))->assertInertia(fn (Assert $page) => $page->where('total', 1));

    $this->actingAs(User::factory()->create());

    $this->get(statusSearchUrl($filter))->assertInertia(fn (Assert $page) => $page->where('total', 3));
});

it('narrows the owner to one status with a status condition, and labels the card', function () {
    $this->actingAs(User::factory()->create());

    $filter = [['type' => 'article', 'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => 'unlisted']]]];

    $this->get(statusSearchUrl($filter))->assertInertia(fn (Assert $page) => $page
        ->where('total', 1)
        ->where('groups.0.items.0.statusLabel', 'Unlisted'));
});

it('never lets a guest use the status field to reach hidden entries', function () {
    $filter = [['type' => 'article', 'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => 'private']]]];

    $this->get(statusSearchUrl($filter))->assertInertia(fn (Assert $page) => $page->where('total', 0));
});

it('keeps drafts out of the owner palette and labels the rest', function () {
    $this->actingAs(User::factory()->create());

    $results = collect($this->getJson('/search/suggest?q=Kettle')->json('results'));

    expect($results->pluck('title')->sort()->values()->all())->toBe(['Kettle private', 'Kettle published', 'Kettle unlisted'])
        ->and($results->firstWhere('title', 'Kettle published')['statusLabel'])->toBeNull()
        ->and($results->firstWhere('title', 'Kettle private')['statusLabel'])->toBe('Private');
});

it('offers a status field with every status on each type', function () {
    expect(SearchSchema::types()['note']['fields']['status']['options'])->toBe(['draft', 'published', 'unlisted', 'private']);
});

it('only offers published entries to mention', function () {
    expect(collect(app(MentionSearch::class)('Kettle'))->pluck('label')->all())->toBe(['Kettle published']);
});

it('gives MCP the owner view: status on an entry, hidden rows off the timeline and search unless asked', function () {
    $this->actingAs(User::factory()->create());

    $entry = callTool(Entry::class, ['url' => '/2026/06/15/kettle-private'])['data'];
    $filter = [['type' => 'article', 'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Kettle']]]];

    expect($entry['status'])->toBe('private')
        ->and($entry)->not->toHaveKey('password')
        ->and(callTool(Timeline::class, ['from' => '2026-06-15'])['data']['count'])->toBe(1)
        ->and(callTool(SearchEntries::class, ['filter' => $filter])['data']['total'])->toBe(1)
        ->and(callTool(SearchEntries::class, ['filter' => $filter, 'status' => 'all'])['data']['total'])->toBe(3);
});
