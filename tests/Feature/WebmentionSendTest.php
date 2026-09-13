<?php

use App\Jobs\SendWebmentions;
use App\Models\Note;
use App\Models\Page;
use App\Models\WebmentionSend;
use App\Support\PortableText;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

// Publishing a post also fetches favicons for the hosts it links to, which
// goes out through Saloon and has nothing to do with what these tests assert.
//
// Sending is off outside production, which is the point of the last test here,
// so everything above it says out loud that it is testing a site that sends.
beforeEach(function () {
    Saloon::fake(['*' => MockResponse::make('', 404)]);
    config(['webmentions.send' => true]);
});

const LINKED = 'https://example.com/post';

const ENDPOINT = 'https://example.com/wm';

/**
 * Fake a target that advertises an endpoint, and that endpoint accepting.
 * Stubs accumulate within a test, so every URL a run touches is set up here.
 */
function fakeReceiver(int $accepts = 202): void
{
    Http::fake([
        LINKED => Http::response('<link rel="webmention" href="'.ENDPOINT.'">'),
        'https://example.com/second' => Http::response('<link rel="webmention" href="'.ENDPOINT.'">'),
        'https://example.com/bare' => Http::response('<p>No endpoint here.</p>'),
        ENDPOINT => Http::response('', $accepts),
    ]);
}

/** A note whose body links to each of $urls. */
function noteLinking(array $urls): Note
{
    return Note::factory()->create([
        'content' => PortableText::fromPlainText('Worth reading: '.implode(' and ', $urls)),
    ]);
}

/** How many times the endpoint was POSTed for $target. */
function sendsFor(string $target): int
{
    $count = 0;

    Http::recorded(function (Request $request) use ($target, &$count) {
        if ($request->method() === 'POST' && ($request->data()['target'] ?? null) === $target) {
            $count++;
        }
    });

    return $count;
}

it('tells a linked site it has been linked to, on publish', function () {
    fakeReceiver();

    // No explicit dispatch: saving a post is what sends, because entries also
    // arrive from Micropub, the API and the sync commands.
    $note = noteLinking([LINKED]);

    expect(sendsFor(LINKED))->toBe(1);

    $send = WebmentionSend::query()->firstWhere('target_url', LINKED);
    expect($send->status)->toBe('sent')
        ->and($send->status_code)->toBe(202)
        ->and($send->endpoint)->toBe(ENDPOINT)
        ->and($send->source_id)->toBe($note->id);
});

it('does not send again when nothing about the post changed', function () {
    fakeReceiver();
    $note = noteLinking([LINKED]);

    // Re-sending an unchanged post is what produces duplicates at the far end.
    (new SendWebmentions($note))->handle();

    expect(sendsFor(LINKED))->toBe(1);
});

it('ignores a save that could not have touched a link', function () {
    fakeReceiver();
    $note = noteLinking([LINKED]);

    $note->update(['occurred_at' => now()->subDay()]);

    expect(sendsFor(LINKED))->toBe(1);
});

it('re-sends everything when the post is edited, so receivers refetch', function () {
    fakeReceiver();
    $note = noteLinking([LINKED]);

    $note->update(['content' => PortableText::fromPlainText('Rewritten, still citing '.LINKED)]);

    expect(sendsFor(LINKED))->toBe(2);
});

it('counts a retitle as an edit, since a receiver parses p-name too', function () {
    fakeReceiver();

    $page = Page::factory()->create([
        'content' => PortableText::fromPlainText('Worth reading: '.LINKED),
    ]);

    $page->update(['title' => 'A new title entirely']);

    expect(sendsFor(LINKED))->toBe(2);
});

it('sends once more to a link that has been taken out', function () {
    fakeReceiver();
    $note = noteLinking([LINKED]);

    $note->update(['content' => PortableText::fromPlainText('Now linking https://example.com/second')]);

    // Without this the far end goes on showing a mention that no longer exists.
    expect(sendsFor(LINKED))->toBe(2)
        ->and(sendsFor('https://example.com/second'))->toBe(1);
});

it('leaves a failed send undelivered so the next run retries it', function () {
    fakeReceiver(accepts: 503);
    $note = noteLinking([LINKED]);

    expect(WebmentionSend::query()->firstWhere('target_url', LINKED)->status)->toBe('failed');

    // Nothing about the post changed, but an undelivered target still goes
    // out: this is the difference between "told them" and "tried and failed".
    (new SendWebmentions($note))->handle();

    expect(sendsFor(LINKED))->toBe(2);
});

it('records a site that takes no webmentions and stops probing it', function () {
    fakeReceiver();
    $note = noteLinking(['https://example.com/bare']);

    (new SendWebmentions($note))->handle();

    $send = WebmentionSend::query()->firstWhere('target_url', 'https://example.com/bare');

    expect($send->status)->toBe('unsupported')
        ->and($send->attempts)->toBe(1)
        ->and(sendsFor('https://example.com/bare'))->toBe(0);
});

// The local database holds the same posts as the live one, so a seed or a test
// run would tell every linked site again, from a URL none of them can fetch.
it('sends nothing at all from a site that is not the live one', function () {
    config(['webmentions.send' => false]);
    fakeReceiver();

    noteLinking([LINKED]);

    expect(sendsFor(LINKED))->toBe(0)
        ->and(WebmentionSend::query()->count())->toBe(0);
});
