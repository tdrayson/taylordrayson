<?php

use App\Jobs\VerifyWebmention;
use App\Models\Note;
use App\Models\Page;
use App\Models\Webmention;
use App\Support\SafeUrl;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * A source page marked up as a reply to $target. Bodies are built rather than
 * fixtured so each test can say exactly what it is testing.
 */
function sourceHtml(string $target, string $property = 'in-reply-to', string $content = 'Good point, I agree.'): string
{
    return <<<HTML
    <div class="h-entry">
        <a class="p-author h-card" href="https://jo.example/">Jo Bloggs</a>
        <a class="u-{$property}" href="{$target}">re</a>
        <div class="e-content">{$content}</div>
        <time class="dt-published" datetime="2026-08-27T10:00:00Z">27 Aug</time>
    </div>
    HTML;
}

/** The public URL of a note, as a sender would have seen it. */
function mentionUrl(Note $note): string
{
    return rtrim(config('app.url'), '/').$note->url();
}

beforeEach(function () {
    Queue::fake();
});

it('advertises its endpoint in the HTML, where a sender will look', function () {
    get('/')->assertOk()->assertSee('rel="webmention"', escape: false);
});

it('accepts a well-formed mention and queues it for verification', function () {
    $note = Note::factory()->create();

    post('/webmention', ['source' => 'https://jo.example/post', 'target' => mentionUrl($note)])
        ->assertStatus(202);

    expect(Webmention::query()->where('source_url', 'https://jo.example/post')->exists())->toBeTrue();
    Queue::assertPushed(VerifyWebmention::class);
});

it('updates the existing row when the same pair is sent again', function () {
    $note = Note::factory()->create();
    $payload = ['source' => 'https://jo.example/post', 'target' => mentionUrl($note)];

    post('/webmention', $payload)->assertStatus(202);
    post('/webmention', $payload)->assertStatus(202);

    // The spec makes a re-send the update mechanism, so it must not duplicate.
    expect(Webmention::count())->toBe(1);
});

it('rejects the targets that are listings rather than posts', function () {
    $base = rtrim(config('app.url'), '/');

    foreach (['/', '/2026', '/2026/08', '/tags/coffee', '/photos'] as $path) {
        post('/webmention', ['source' => 'https://jo.example/post', 'target' => $base.$path])
            ->assertStatus(400);
    }

    expect(Webmention::count())->toBe(0);
});

it('rejects a target on somebody else s site, which would make it a relay', function () {
    post('/webmention', ['source' => 'https://jo.example/post', 'target' => 'https://example.org/theirs'])
        ->assertStatus(400);
});

it('rejects a source on this site, so an internal link is not also a mention', function () {
    $note = Note::factory()->create();
    $base = rtrim(config('app.url'), '/');

    post('/webmention', ['source' => $base.'/somewhere', 'target' => mentionUrl($note)])
        ->assertStatus(400);
});

it('refuses to fetch a source pointed at the network the server sits on', function () {
    // IP literals, so the guard is exercised without a DNS lookup. This is the
    // check that stops the endpoint being a request forger with a public URL.
    foreach ([
        'http://127.0.0.1/x',
        'http://169.254.169.254/latest/meta-data',
        'http://192.168.1.1/',
        'http://10.0.0.5/internal',
        'http://[::1]/x',
        'ftp://198.51.100.9/x',
    ] as $source) {
        expect(SafeUrl::fetchable($source))->toBeFalse($source);
    }

    expect(SafeUrl::fetchable('http://93.184.216.34/'))->toBeTrue();
});

it('rejects a malformed or missing pair', function () {
    $note = Note::factory()->create();

    post('/webmention', ['target' => mentionUrl($note)])->assertStatus(400);
    post('/webmention', ['source' => 'not a url', 'target' => mentionUrl($note)])->assertStatus(400);
    post('/webmention', ['source' => mentionUrl($note), 'target' => mentionUrl($note)])->assertStatus(400);
});

it('accepts a mention against a published page but not a draft one', function () {
    $base = rtrim(config('app.url'), '/');

    post('/webmention', [
        'source' => 'https://jo.example/post',
        'target' => $base.'/'.Page::factory()->create()->slug,
    ])->assertStatus(202);

    post('/webmention', [
        'source' => 'https://jo.example/other',
        'target' => $base.'/'.Page::factory()->draft()->create()->slug,
    ])->assertStatus(400);
});
