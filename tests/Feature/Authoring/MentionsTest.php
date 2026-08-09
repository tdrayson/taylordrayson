<?php

use App\Actions\ResolveMentions;
use App\Models\Article;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;

/** Portable Text content containing a single mention of the given target. */
function contentMentioning(string $kind, int|string $id): array
{
    return [[
        '_type' => 'block',
        '_key' => 'b1',
        'style' => 'normal',
        'children' => [
            ['_type' => 'span', '_key' => 's1', 'text' => 'See ', 'marks' => []],
            ['_type' => 'mention', '_key' => 'm1', 'kind' => $kind, 'id' => $id],
        ],
    ]];
}

it('resolves a mention to the target title and url', function () {
    $article = Article::factory()->create(['title' => 'How OG images work', 'published' => true]);

    $resolved = app(ResolveMentions::class)(contentMentioning('article', $article->id));

    expect($resolved["article:{$article->id}"])->toMatchArray([
        'title' => 'How OG images work',
        'kind' => 'article',
        'exists' => true,
    ])->and($resolved["article:{$article->id}"]['url'])->toBe($article->url());
});

it('follows a rename, which is the whole reason the title is not stored', function () {
    $article = Article::factory()->create(['title' => 'Old title', 'published' => true]);
    $content = contentMentioning('article', $article->id);

    $article->update(['title' => 'New title']);

    expect(app(ResolveMentions::class)($content)["article:{$article->id}"]['title'])->toBe('New title');
});

it('reports a deleted target as gone rather than showing a stale name', function () {
    $article = Article::factory()->create(['published' => true]);
    $content = contentMentioning('article', $article->id);
    $id = $article->id;

    $article->delete();

    expect(app(ResolveMentions::class)($content)["article:{$id}"])->toMatchArray([
        'title' => null,
        'url' => null,
        'exists' => false,
    ]);
});

it('hides an unpublished target from guests but resolves it for the owner', function () {
    $draft = Article::factory()->create(['title' => 'Secret', 'published' => false]);
    $content = contentMentioning('article', $draft->id);

    expect(app(ResolveMentions::class)($content)["article:{$draft->id}"]['exists'])->toBeFalse();

    $this->actingAs(User::factory()->create());

    expect(app(ResolveMentions::class)($content)["article:{$draft->id}"])->toMatchArray([
        'title' => 'Secret',
        'exists' => true,
    ]);
});

it('resolves a page to its slug url', function () {
    $page = Page::factory()->create(['title' => 'About', 'slug' => 'about', 'published' => true]);

    expect(app(ResolveMentions::class)(contentMentioning('page', $page->id))["page:{$page->id}"])
        ->toMatchArray(['title' => 'About', 'url' => '/about', 'exists' => true]);
});

it('resolves mentions nested inside a callout', function () {
    $project = Project::factory()->create(['title' => 'A project']);

    $content = [[
        '_type' => 'callout',
        '_key' => 'c1',
        'variant' => 'note',
        'children' => contentMentioning('project', $project->id),
    ]];

    expect(app(ResolveMentions::class)($content)["project:{$project->id}"]['exists'])->toBeTrue();
});

it('looks up a repeated target only once', function () {
    $article = Article::factory()->create(['published' => true]);

    $content = [
        ...contentMentioning('article', $article->id),
        ...contentMentioning('article', $article->id),
    ];

    expect(app(ResolveMentions::class)($content))->toHaveCount(1);
});

it('ignores a kind nobody can mention', function () {
    // You do not mention last night's sleep mid-sentence.
    $resolved = app(ResolveMentions::class)(contentMentioning('sleep', 1));

    expect($resolved['sleep:1']['exists'])->toBeFalse();
});

it('requires a session for the mention search', function () {
    $this->getJson('/mentions/search?q=a')->assertUnauthorized();
});

it('groups mention candidates by kind for the menu', function () {
    Article::factory()->create(['title' => 'Alpha article', 'published' => true]);
    Page::factory()->create(['title' => 'Alpha page', 'published' => true]);
    Project::factory()->create(['title' => 'Alpha project']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/mentions/search?q=Alpha')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('group')->unique()->values()->all())
        ->toBe(['Articles', 'Pages', 'Projects']);
});

it('caps each kind before concatenating, so no group is starved on an empty query', function () {
    Article::factory()->count(8)->create(['published' => true]);
    Page::factory()->count(8)->create(['published' => true]);

    $data = collect($this->actingAs(User::factory()->create())
        ->getJson('/mentions/search')
        ->json('data'));

    // Capping the flat list instead would let articles swallow the whole limit.
    expect($data->where('kind', 'article'))->toHaveCount(5)
        ->and($data->where('kind', 'page'))->toHaveCount(5);
});
