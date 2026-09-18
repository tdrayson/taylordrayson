<?php

use App\Enums\CommentStatus;
use App\Enums\WebmentionKind;
use App\Jobs\VerifyWebmention;
use App\Models\Note;
use App\Models\Webmention;
use App\Presenters\Conversation;
use App\Support\PortableText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

/**
 * The receiving half of a salmention: their post replied to mine, people replied
 * to theirs, and re-fetching their page is the only way that thread reaches this
 * one.
 *
 * example.com throughout, because a source's DNS is resolved before it is
 * fetched and a reserved TLD never resolves. Http::fake() still means nothing
 * leaves the machine.
 */
const THEIR_POST = 'https://example.com/their-post';

beforeEach(function () {
    // Publishing fetches favicons for the hosts an entry links to, which has
    // nothing to do with anything asserted here.
    Saloon::fake(['*' => MockResponse::make('', 404)]);

    config([
        'webmentions.send' => true,
        'webmentions.trusted_hosts' => ['example.com', 'chris.example.com', 'robin.example.com'],
    ]);

    // One stub, re-read per request: Http::fake() merges stubs and the first
    // registered one wins, so a second call cannot change what a URL serves,
    // which is exactly what a re-check test needs to do.
    Http::fake([THEIR_POST => fn () => Http::response(servePage())]);
});

/** What their page serves right now. Passing $html replaces it. */
function servePage(?string $html = null): string
{
    static $body = '';

    if ($html !== null) {
        $body = $html;
    }

    return $body;
}

/** An entry's URL as a sender would address it. */
function salmentionTarget(Note $note): string
{
    return rtrim((string) config('app.url'), '/').$note->url();
}

/** Their post replying to mine, carrying $thread as its own comment thread. */
function theirPost(string $target, string $thread = ''): string
{
    return <<<HTML
    <html><body><div class="h-entry">
        <a class="p-author h-card" href="https://example.com/jo">Jo Bloggs</a>
        <a class="u-in-reply-to" href="{$target}">re</a>
        <div class="e-content">Good point, and here is mine.</div>
        {$thread}
    </div></body></html>
    HTML;
}

/** One response nested in their thread, marked up the way a comment thread is. */
function nestedCite(string $author, string $body, ?string $url = null, string $property = 'in-reply-to'): string
{
    $permalink = $url === null ? '' : '<a class="u-url" href="'.$url.'">permalink</a>';

    return <<<HTML
    <div class="p-comment h-cite">
        <a class="p-author h-card" href="https://{$author}.example.com/">{$author}</a>
        {$permalink}
        <a class="u-{$property}" href="{{THEIRS}}">re</a>
        <div class="e-content">{$body}</div>
    </div>
    HTML;
}

/** Fetch their page and file what it says, first time or on a re-check. */
function recheck(Note $note, string $html): Webmention
{
    $target = salmentionTarget($note);

    servePage(str_replace('{{THEIRS}}', THEIR_POST, $html));

    $mention = Webmention::query()->updateOrCreate(
        ['source_url' => THEIR_POST, 'target_url' => $target],
        ['last_checked_at' => null],
    );

    app()->call([new VerifyWebmention($mention->id), 'handle']);

    return $mention->fresh();
}

/** The responses their page carried, as rows of ours. */
function nestedRows(): Collection
{
    return Webmention::query()->where('parent_source_url', THEIR_POST)->orderBy('id')->get();
}

it('reads the responses nested inside a source it fetches', function () {
    $note = Note::factory()->create();

    recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed.', 'https://chris.example.com/1')));

    $nested = nestedRows()->sole();

    expect($nested->source_url)->toBe('https://chris.example.com/1')
        ->and($nested->parent_source_url)->toBe(THEIR_POST)
        ->and($nested->target_url)->toBe(salmentionTarget($note))
        ->and($nested->author_name)->toBe('chris')
        ->and($nested->kind)->toBe(WebmentionKind::Reply->value)
        ->and(PortableText::plainText($nested->content))->toContain('Agreed.')
        ->and($nested->target_id)->toBe($note->id);
});

it('shows a nested response indented under the mention that carried it', function () {
    $note = Note::factory()->create();

    $parent = recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed.', 'https://chris.example.com/1')));

    $items = collect(Conversation::for($note)->responses)->keyBy('sourceUrl');

    expect($items['https://chris.example.com/1']->parentItemId)->toBe('mention-'.$parent->id)
        ->and($items[THEIR_POST]->parentItemId)->toBeNull();
});

it('updates a nested response on a re-check instead of storing it twice', function () {
    $note = Note::factory()->create();

    recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed.', 'https://chris.example.com/1')));
    recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed, on reflection.', 'https://chris.example.com/1')));

    expect(PortableText::plainText(nestedRows()->sole()->content))->toContain('on reflection');
});

it('stops showing a nested response that has left the thread', function () {
    $note = Note::factory()->create();

    $thread = nestedCite('chris', 'Agreed.', 'https://chris.example.com/1')
        .nestedCite('robin', 'Not sure.', 'https://robin.example.com/1');

    recheck($note, theirPost(salmentionTarget($note), $thread));
    expect(nestedRows())->toHaveCount(2);

    recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed.', 'https://chris.example.com/1')));

    expect(nestedRows()->pluck('source_url')->all())->toBe(['https://chris.example.com/1']);
});

it('takes the nested responses with the mention when the source stops linking here', function () {
    $note = Note::factory()->create();

    $parent = recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed.', 'https://chris.example.com/1')));

    $parent->delete();

    expect(nestedRows())->toHaveCount(0);
});

it('keys a nested response with no permalink on who said what, so a re-check does not duplicate it', function () {
    $note = Note::factory()->create();

    recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed.')));
    recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed.')));

    expect(nestedRows()->sole()->source_url)->toStartWith(THEIR_POST.'#salmention-');
});

it('ignores a nested response written by me, which is already on the page', function () {
    $note = Note::factory()->create();

    $mine = <<<'HTML'
    <div class="p-comment h-cite">
        <a class="p-author h-card" href="{{HOME}}/about">Taylor Drayson</a>
        <div class="e-content">That was my point exactly.</div>
    </div>
    HTML;

    recheck($note, theirPost(salmentionTarget($note), str_replace('{{HOME}}', rtrim((string) config('app.url'), '/'), $mine)));

    expect(nestedRows())->toHaveCount(0);
});

it('reads one level of nesting and no further', function () {
    $note = Note::factory()->create();

    $inner = nestedCite('robin', 'A reply to a reply.', 'https://robin.example.com/1');
    $outer = <<<HTML
    <div class="p-comment h-cite">
        <a class="p-author h-card" href="https://chris.example.com/">chris</a>
        <a class="u-url" href="https://chris.example.com/1">permalink</a>
        <div class="e-content">Agreed.</div>
        {$inner}
    </div>
    HTML;

    recheck($note, theirPost(salmentionTarget($note), $outer));

    expect(nestedRows()->pluck('source_url')->all())->toBe(['https://chris.example.com/1']);
});

it('caps how many responses one source can add', function () {
    $note = Note::factory()->create();

    $thread = collect(range(1, 30))
        ->map(fn (int $i): string => nestedCite('chris', "Reply {$i}.", "https://chris.example.com/{$i}"))
        ->implode('');

    recheck($note, theirPost(salmentionTarget($note), $thread));

    expect(nestedRows())->toHaveCount(20);
});

it('leaves a response alone when its author already sent it here themselves', function () {
    $note = Note::factory()->create();

    Webmention::query()->create([
        'source_url' => 'https://chris.example.com/1',
        'target_url' => salmentionTarget($note),
        'kind' => WebmentionKind::Reply->value,
        'status' => CommentStatus::Approved,
    ]);

    recheck($note, theirPost(salmentionTarget($note), nestedCite('chris', 'Agreed.', 'https://chris.example.com/1')));

    expect(nestedRows())->toHaveCount(0)
        ->and(Webmention::query()->where('source_url', 'https://chris.example.com/1')->sole()->parent_source_url)->toBeNull();
});
