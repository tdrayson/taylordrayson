<?php

use App\Actions\Og\BuildEntryOgData;
use App\Enums\EntryStatus;
use App\Models\Article;
use App\Models\Page;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Models\User;
use App\Support\PortableText;
use Inertia\Testing\AssertableInertia as Assert;

/** An article, not a note: a note's meta description is its body, which the spec lets the head show. */
function privateArticle(): Article
{
    return Article::factory()->create([
        'title' => 'Kept close',
        'excerpt' => 'A public teaser',
        'content' => PortableText::fromPlainText('Private body words'),
        'slug' => 'kept-close',
        'occurred_at' => '2026-06-15 09:00:00',
        'status' => EntryStatus::Private,
        'password' => 'hunter2',
    ]);
}

it('shows a guest the prompt with no body in props or HTML, and no caching', function () {
    privateArticle();

    $response = $this->get('/2026/06/15/kept-close');

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertDontSee('Private body words')
        ->assertInertia(fn (Assert $page) => $page
            ->where('locked', true)
            ->where('entry', null)
            ->missing('fields')
            ->missing('media')
            ->has('og.title'));
});

it('refuses a wrong password and unlocks on the right one for the rest of the session', function () {
    $article = privateArticle();
    $unlock = "/unlock/article/{$article->id}";

    $this->from('/2026/06/15/kept-close')->post($unlock, ['password' => 'nope'])
        ->assertRedirect('/2026/06/15/kept-close')
        ->assertSessionHasErrors('password');

    $this->from('/2026/06/15/kept-close')->post($unlock, ['password' => 'hunter2'])->assertSessionHasNoErrors();

    $this->get('/2026/06/15/kept-close')->assertInertia(fn (Assert $page) => $page->where('locked', false)->has('entry.content'));
    $this->get('/2026/06/15/kept-close')->assertInertia(fn (Assert $page) => $page->where('locked', false));
});

it('throttles password guesses', function () {
    $article = privateArticle();

    foreach (range(1, 5) as $attempt) {
        $this->post("/unlock/article/{$article->id}", ['password' => 'nope']);
    }

    $this->post("/unlock/article/{$article->id}", ['password' => 'hunter2'])->assertTooManyRequests();
});

it('opens a private entry for the owner without a prompt', function () {
    privateArticle();

    $this->actingAs(User::factory()->create())
        ->get('/2026/06/15/kept-close')
        ->assertInertia(fn (Assert $page) => $page->where('locked', false)->has('entry'));
});

it('locks a private page the same way', function () {
    $page = Page::factory()->create([
        'slug' => 'vault',
        'excerpt' => 'A page teaser',
        'content' => PortableText::fromPlainText('Page secret words'),
        'status' => EntryStatus::Private,
        'password' => 'hunter2',
    ]);

    $this->get('/vault')->assertOk()->assertDontSee('Page secret words')
        ->assertInertia(fn (Assert $inertia) => $inertia->where('locked', true)->missing('content'));

    $this->post("/unlock/page/{$page->id}", ['password' => 'hunter2']);

    $this->get('/vault')->assertInertia(fn (Assert $inertia) => $inertia->where('locked', false)->has('content'));
});

it('404s a draft page for a guest', function () {
    Page::factory()->draft()->create(['slug' => 'wip']);

    $this->get('/wip')->assertNotFound();
});

it('gives a wrong password guess the same response whether the entry is a draft, published, or private', function (EntryStatus $status) {
    $article = Article::factory()->create([
        'status' => $status,
        'password' => $status === EntryStatus::Private ? 'hunter2' : null,
    ]);

    $this->post("/unlock/article/{$article->id}", ['password' => 'nope'])
        ->assertStatus(302)
        ->assertSessionHasErrors('password');
})->with([
    'draft' => [EntryStatus::Draft],
    'published' => [EntryStatus::Published],
    'private' => [EntryStatus::Private],
]);

it('keeps a private entry\'s body out of its own generated OG image data', function () {
    $article = privateArticle();

    $entry = TimelineEntry::query()
        ->withoutGlobalScope(ListedScope::class)
        ->where('dataset', $article->getMorphClass())
        ->where('entry_id', $article->id)
        ->with('entry')
        ->sole();

    $data = app(BuildEntryOgData::class)($entry, fn (): ?string => null);

    expect(json_encode($data))->not->toContain('Private body words');
});
