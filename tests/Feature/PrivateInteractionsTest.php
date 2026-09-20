<?php

use App\Actions\Syndicated\PullStravaResponses;
use App\Enums\CommentStatus;
use App\Enums\EntryStatus;
use App\Enums\Source;
use App\Jobs\SendWebmentions;
use App\Jobs\VerifyWebmention;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Mention;
use App\Models\Note;
use App\Models\Page;
use App\Models\Reaction;
use App\Models\Sleep;
use App\Models\Webmention;
use App\Support\PortableText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

/**
 * A private entry takes comments, reactions, webmentions and mentions, shown to
 * whoever has unlocked it, and sends none of its own.
 */
beforeEach(function () {
    Queue::fake();
});

function privateNote(array $attributes = []): Note
{
    return Note::factory()->create([
        'status' => EntryStatus::Private,
        'password' => 'hunter2',
        ...$attributes,
    ]);
}

/** A comment payload with a nonce old enough to clear the time-on-form check. */
function privateCommentPayload(): array
{
    $nonce = Str::uuid()->toString();
    Cache::put('nonce:comment:'.$nonce, now()->subSeconds(30)->timestamp, 3600);

    return ['author_name' => 'Jo', 'body' => 'This is a real comment with real words in it.', 'nonce' => $nonce];
}

function absoluteUrl(string $path): string
{
    return rtrim(config('app.url'), '/').$path;
}

function paragraphLinkingTo(string $href, string $label = 'this'): array
{
    return [[
        '_type' => 'block',
        '_key' => 'block-1',
        'style' => 'normal',
        'markDefs' => [['_key' => 'link-1', '_type' => 'link', 'href' => $href]],
        'children' => [PortableText::span('See '), PortableText::span($label, ['link-1'])],
    ]];
}

it('refuses a comment and a reaction on a locked private entry', function () {
    $note = privateNote();

    $this->postJson("/comments/note/{$note->id}", privateCommentPayload())->assertNotFound();
    $this->postJson("/reactions/note/{$note->id}", ['type' => 'love'])->assertNotFound();

    expect(Comment::count())->toBe(0)
        ->and(Reaction::count())->toBe(0);
});

it('accepts a comment and a reaction once the private entry is unlocked', function () {
    $note = privateNote();

    $this->withSession([$note->unlockKey() => true]);

    $this->postJson("/comments/note/{$note->id}", privateCommentPayload())->assertCreated();
    $this->postJson("/reactions/note/{$note->id}", ['type' => 'love'])->assertSuccessful();

    expect(Comment::count())->toBe(1)
        ->and(Reaction::count())->toBe(1);
});

it('withholds received webmentions and mentions until the private entry is unlocked', function () {
    $note = privateNote();
    $note->webmentions()->create([
        'source_url' => 'https://jo.example/post',
        'target_url' => absoluteUrl($note->url()),
        'kind' => 'reply',
        'author_name' => 'Jo Webmentioner',
        'content' => PortableText::fromPlainText('A reply from elsewhere'),
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
    ]);
    Article::factory()->create(['title' => 'The Linking Article', 'content' => paragraphLinkingTo($note->url())]);

    $this->get($note->url())
        ->assertInertia(fn (Assert $page) => $page->where('locked', true)->missing('conversation'))
        ->assertDontSee('Jo Webmentioner')
        ->assertDontSee('A reply from elsewhere')
        ->assertDontSee('The Linking Article');

    $this->withSession([$note->unlockKey() => true])
        ->get($note->url())
        ->assertInertia(fn (Assert $page) => $page->has('conversation.responses', 2));
});

it('accepts and verifies an incoming webmention for a private entry', function () {
    $note = privateNote();
    $target = absoluteUrl($note->url());
    $source = 'https://example.com/reply';

    $this->post('/webmention', ['source' => $source, 'target' => $target])->assertStatus(202);

    Http::fake([$source => Http::response(
        "<html><body><div class=\"h-entry\"><a class=\"u-in-reply-to\" href=\"{$target}\">re</a><div class=\"e-content\">Nice.</div></div></body></html>",
    )]);

    app()->call([new VerifyWebmention(Webmention::query()->sole()->id), 'handle']);

    expect(Webmention::query()->sole())
        ->verified_at->not->toBeNull()
        ->target_id->toBe($note->id);
});

it('sends nothing and records no mentions from a private entry', function () {
    $sleep = Sleep::factory()->create();

    privateNote(['content' => PortableText::fromPlainText(
        'See https://example.com/post and '.absoluteUrl($sleep->url()),
    )]);

    Queue::assertNotPushed(SendWebmentions::class);
    expect(Mention::count())->toBe(0);
});

it('records a mention on a private entry linked from a public one', function () {
    $article = Article::factory()->create(['status' => EntryStatus::Private, 'password' => 'hunter2']);

    Note::factory()->create(['content' => paragraphLinkingTo($article->url())]);

    expect($article->mentions()->count())->toBe(1);
});

it('records its mentions and sends its webmentions once published, with the content unchanged', function (EntryStatus $from, EntryStatus $to) {
    $sleep = Sleep::factory()->create();
    $note = Note::factory()->create([
        'status' => $from,
        'password' => $from === EntryStatus::Private ? 'hunter2' : null,
        'content' => PortableText::fromPlainText('See https://example.com/post and '.absoluteUrl($sleep->url())),
    ]);

    Queue::assertNotPushed(SendWebmentions::class);
    expect($sleep->mentions()->count())->toBe(0);

    $note->update(['status' => $to]);

    Queue::assertPushed(SendWebmentions::class, 1);
    expect($sleep->mentions()->count())->toBe(1);
})->with([
    'private to published' => [EntryStatus::Private, EntryStatus::Published],
    'private to unlisted' => [EntryStatus::Private, EntryStatus::Unlisted],
    'draft to published' => [EntryStatus::Draft, EntryStatus::Published],
]);

it('sends nothing again when a published entry is only unlisted', function () {
    $note = Note::factory()->create(['content' => PortableText::fromPlainText('See https://example.com/post')]);

    $note->update(['status' => EntryStatus::Unlisted]);

    Queue::assertPushed(SendWebmentions::class, 1);
});

it('keeps the mentions an entry made and received when it goes private', function () {
    $sleep = Sleep::factory()->create();
    $article = Article::factory()->create([
        'status' => EntryStatus::Published,
        'content' => paragraphLinkingTo($sleep->url()),
    ]);
    $note = Note::factory()->create(['content' => paragraphLinkingTo($article->url())]);

    $article->update(['status' => EntryStatus::Private, 'password' => 'hunter2']);
    $note->update(['content' => paragraphLinkingTo($article->url(), 'still this')]);

    expect($sleep->mentions()->count())->toBe(1)
        ->and($article->mentions()->count())->toBe(1);
});

it('records nothing new when a private entry adds a link, and removes nothing', function () {
    $sleep = Sleep::factory()->create();
    $page = Page::factory()->create();
    $article = Article::factory()->create([
        'status' => EntryStatus::Published,
        'content' => paragraphLinkingTo($sleep->url()),
    ]);

    $article->update(['status' => EntryStatus::Private, 'password' => 'hunter2']);
    $article->update(['content' => paragraphLinkingTo('/'.$page->slug)]);

    expect($sleep->mentions()->count())->toBe(1)
        ->and($page->mentions()->count())->toBe(0);
});

it('treats an unlisted entry exactly like a published one', function () {
    $sleep = Sleep::factory()->create();
    $unlisted = Note::factory()->create([
        'status' => EntryStatus::Unlisted,
        'content' => PortableText::fromPlainText('See https://example.com/post and '.absoluteUrl($sleep->url())),
    ]);

    Queue::assertPushed(SendWebmentions::class);
    expect($sleep->mentions()->count())->toBe(1);

    $this->postJson("/comments/note/{$unlisted->id}", privateCommentPayload())->assertCreated();
    $this->postJson("/reactions/note/{$unlisted->id}", ['type' => 'love'])->assertSuccessful();
    $this->post('/webmention', [
        'source' => 'https://jo.example/post',
        'target' => absoluteUrl($unlisted->url()),
    ])->assertStatus(202);

    Note::factory()->create(['content' => paragraphLinkingTo($unlisted->url())]);

    expect($unlisted->mentions()->count())->toBe(1);
});

it('still imports Strava responses onto a private activity', function () {
    config(['services.strava.refresh_token' => 'test-token']);
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/api/v3/activities/778/kudos' => MockResponse::make([['firstname' => 'Justin', 'lastname' => 'M.']]),
        '/api/v3/activities/778/comments' => MockResponse::make([]),
    ]);

    $activity = Activity::factory()->create([
        'source' => Source::Strava->value,
        'source_id' => '778',
        'status' => EntryStatus::Private,
        'password' => 'hunter2',
    ]);

    app(PullStravaResponses::class)($activity);

    expect($activity->syndicatedResponses()->count())->toBe(1);

    $this->withSession([$activity->unlockKey() => true])
        ->get($activity->url())
        ->assertInertia(fn (Assert $page) => $page->where('conversation.responses.0.source', 'strava'));
});
