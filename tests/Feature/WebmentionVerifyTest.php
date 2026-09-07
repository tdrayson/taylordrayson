<?php

use App\Actions\Webmentions\ParseMentionSource;
use App\Enums\CommentStatus;
use App\Enums\WebmentionKind;
use App\Jobs\VerifyWebmention;
use App\Models\Note;
use App\Models\Reaction;
use App\Models\Webmention;
use App\Presenters\Conversation;
use App\Support\PortableText;
use Illuminate\Support\Facades\Http;

/**
 * example.com is used rather than a .example domain because the verifier
 * resolves a source's DNS before fetching it, and reserved TLDs never resolve.
 * Http::fake() still means no request leaves the machine.
 */
const SOURCE = 'https://example.com/reply';

/** An h-entry responding to $target through $property, with $content as its body. */
function mentionSource(string $target, string $property, string $content): string
{
    return <<<HTML
    <html><body><div class="h-entry">
        <a class="p-author h-card" href="https://example.com/jo">Jo Bloggs</a>
        <a class="u-{$property}" href="{$target}">re</a>
        <div class="e-content">{$content}</div>
        <time class="dt-published" datetime="2026-08-27T10:00:00Z">27 Aug</time>
    </div></body></html>
    HTML;
}

/**
 * An h-entry that RSVPs to $target. Unlike the other kinds an RSVP is not a
 * URL property of its own: it is an in-reply-to carrying a p-rsvp value.
 */
function rsvpSource(string $target, string $answer = 'yes'): string
{
    return <<<HTML
    <html><body><div class="h-entry">
        <a class="p-author h-card" href="https://example.com/jo">Jo Bloggs</a>
        <a class="u-in-reply-to" href="{$target}">the event</a>
        <data class="p-rsvp" value="{$answer}">{$answer}</data>
    </div></body></html>
    HTML;
}

/** Receive and verify a mention from a source serving $html. */
function verify(Note $note, ?string $html, int $status = 200): ?Webmention
{
    $target = rtrim(config('app.url'), '/').$note->url();

    Http::fake([SOURCE => Http::response($html ?? mentionSource($target, 'in-reply-to', 'Nice one.'), $status)]);

    $mention = Webmention::query()->create(['source_url' => SOURCE, 'target_url' => $target]);

    (new VerifyWebmention($mention->id))->handle(app(ParseMentionSource::class));

    return $mention->fresh();
}

it('reads the author and the body out of a real reply', function () {
    $note = Note::factory()->create();

    $mention = verify($note, null);

    expect($mention)->not->toBeNull()
        ->kind->toBe(WebmentionKind::Reply->value)
        ->author_name->toBe('Jo Bloggs')
        ->author_url->toBe('https://example.com/jo')
        ->status->toBe(CommentStatus::Pending)
        ->and(PortableText::plainText($mention->content))->toBe('Nice one.')
        ->and($mention->verified_at)->not->toBeNull()
        ->and($mention->target_id)->toBe($note->id);
});

it('drops a mention whose source does not actually link here', function () {
    $note = Note::factory()->create();

    // The whole point of verification: without it, anyone could attribute any
    // page to any URL.
    expect(verify($note, '<div class="h-entry">I linked to somebody else.</div>'))->toBeNull();
});

it('drops a mention whose source has gone', function () {
    $note = Note::factory()->create();

    expect(verify($note, 'gone', 410))->toBeNull();
});

it('shows a like as a one-line gesture rather than an empty reply', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    verify($note, mentionSource($target, 'like-of', ''))->update(['status' => CommentStatus::Approved]);

    $like = Conversation::for($note)->responses[0];

    expect($like->kind)->toBe(WebmentionKind::Like->value)
        ->and($like->authorName)->toBe('Jo Bloggs')
        // No prose, so it renders as one line rather than a block. And it is
        // not an anonymous +1 on the emoji bar either.
        ->and($like->body)->toBeNull()
        ->and(Reaction::count())->toBe(0);
});

it('reads a single emoji reply as a gesture carrying that emoji', function (string $emoji) {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    verify($note, mentionSource($target, 'in-reply-to', $emoji))
        ->update(['status' => CommentStatus::Approved]);

    $reacji = Conversation::for($note)->responses[0];

    // Carries the emoji actually sent, never rounded to the nearest offered
    // reaction, and never rendered as a one-line reply of its own text.
    expect($reacji->kind)->toBe(WebmentionKind::Reacji->value)
        ->and($reacji->emoji)->toBe($emoji)
        ->and($reacji->body)->toBeNull();
})->with([
    'one we offer' => "\u{1F602}",
    // Five codepoints joined by zero-width joiners, and a thumb carrying a skin
    // tone: one grapheme each, several codepoints each. The mb_strlen trap.
    'ZWJ sequence' => "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}",
    'skin tone' => "\u{1F44D}\u{1F3FD}",
]);

it('treats a real sentence as a reply, not a reaction', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    expect(verify($note, mentionSource($target, 'in-reply-to', 'Yes! 🎉'))->kind)
        ->toBe(WebmentionKind::Reply->value);
});

it('is a mention, not a reply, when the reply is to somebody else', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $html = <<<HTML
    <div class="h-entry">
        <a class="u-in-reply-to" href="https://example.com/someone-else">re</a>
        <div class="e-content">Related to <a href="{$target}">this</a>.</div>
    </div>
    HTML;

    expect(verify($note, $html)->kind)->toBe(WebmentionKind::Mention->value);
});

it('trusts a site it has approved before', function () {
    $note = Note::factory()->create();

    Webmention::query()->create([
        'source_url' => 'https://example.com/older',
        'target_url' => 'https://example.com/x',
        'author_url' => 'https://example.com/jo',
        'status' => CommentStatus::Approved,
    ]);

    expect(verify($note, null)->status)->toBe(CommentStatus::Approved);
});

it('records what each kind of mention claims to be', function (string $property, WebmentionKind $kind) {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    expect(verify($note, mentionSource($target, $property, 'Worth a look.'))->kind)->toBe($kind->value);
})->with([
    'reply' => ['in-reply-to', WebmentionKind::Reply],
    'like' => ['like-of', WebmentionKind::Like],
    'repost' => ['repost-of', WebmentionKind::Repost],
    'bookmark' => ['bookmark-of', WebmentionKind::Bookmark],
]);

/**
 * The check is that the property points *here*, not merely that it exists:
 * a post bookmarking somebody else while linking to me is a mention, not a
 * claim that it bookmarked me.
 */
it('does not take a response property aimed at someone else as a response to me', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $html = <<<HTML
    <html><body><div class="h-entry">
        <a class="p-author h-card" href="https://example.com/jo">Jo Bloggs</a>
        <a class="u-bookmark-of" href="https://example.com/elsewhere">their post</a>
        <div class="e-content">See also <a href="{$target}">this</a>.</div>
    </div></body></html>
    HTML;

    expect(verify($note, $html)->kind)->toBe(WebmentionKind::Mention->value);
});

it('records an RSVP as an RSVP rather than as the reply it is marked up as', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    expect(verify($note, rsvpSource($target))->kind)->toBe(WebmentionKind::Rsvp->value);
});

it('falls back to a bare mention when the source only links here', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $html = <<<HTML
    <html><body><div class="h-entry">
        <a class="p-author h-card" href="https://example.com/jo">Jo Bloggs</a>
        <div class="e-content">I was reading <a href="{$target}">this</a> today.</div>
    </div></body></html>
    HTML;

    expect(verify($note, $html)->kind)->toBe(WebmentionKind::Mention->value);
});

it('keeps a mention when the source is merely unreachable', function () {
    // Their outage is not a retraction. Deleting on any failed response meant
    // a re-send during a blip destroyed the mention for good. A note each,
    // because one source may only mention one target once.
    expect(verify(Note::factory()->create(), null, 500))->not->toBeNull()
        ->and(verify(Note::factory()->create(), null, 403))->not->toBeNull();
});

it('removes a mention when the source is gone for good', function () {
    expect(verify(Note::factory()->create(), null, 404))->toBeNull()
        ->and(verify(Note::factory()->create(), null, 410))->toBeNull();
});

it('refuses a source that only mentions the target without linking to it', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    // The URL in prose, in a script, and in a comment. A string search over the
    // raw HTML called all three a link.
    $html = <<<HTML
    <html><body><div class="h-entry">
        <a class="p-author h-card" href="https://example.com/jo">Jo Bloggs</a>
        <div class="e-content">Have a look at {$target} sometime.</div>
        <script>var seen = "{$target}";</script>
        <!-- {$target} -->
    </div></body></html>
    HTML;

    expect(verify($note, $html))->toBeNull();
});

it('refuses a link to a longer URL that merely starts with the target', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    expect(verify($note, mentionSource($target.'-and-then-some', 'in-reply-to', 'Nice one.')))->toBeNull();
});

it('will not follow a redirect into a private address', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    Http::fake([SOURCE => Http::response('', 302, ['Location' => 'http://127.0.0.1/metadata'])]);

    $mention = Webmention::query()->create(['source_url' => SOURCE, 'target_url' => $target]);

    (new VerifyWebmention($mention->id))->handle(app(ParseMentionSource::class));

    // Nothing was fetched from the redirect target, so nothing was verified.
    expect($mention->fresh()?->verified_at)->toBeNull();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
});

it('trusts a host only when it is the same host, not a substring of one', function () {
    // An approved mention from indieweb.org used to make dieweb.org trusted,
    // because the check was `author_url like %dieweb.org%`.
    Webmention::query()->create([
        'source_url' => 'https://indieweb.org/a',
        'target_url' => 'https://example.test/x',
        'author_url' => 'https://indieweb.org/Jo',
        'author_host' => 'indieweb.org',
        'status' => CommentStatus::Approved,
    ]);

    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $html = str_replace('https://example.com/jo', 'https://dieweb.org/imposter', mentionSource($target, 'in-reply-to', 'Trust me.'));

    expect(verify($note, $html))->status->toBe(CommentStatus::Pending);
});

it('lets a configured host through without waiting to be approved', function () {
    config(['webmentions.trusted_hosts' => ['known.example']]);

    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();
    $html = str_replace('https://example.com/jo', 'https://known.example/me', mentionSource($target, 'in-reply-to', 'Hello.'));

    expect(verify($note, $html))->status->toBe(CommentStatus::Approved);
});

it('does not extend the allowlist to a host that merely starts with a trusted one', function () {
    config(['webmentions.trusted_hosts' => ['known.example']]);

    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();
    $html = str_replace('https://example.com/jo', 'https://known.example.evil.tld/me', mentionSource($target, 'in-reply-to', 'Hello.'));

    expect(verify($note, $html))->status->toBe(CommentStatus::Pending);
});

it('keeps the links and quotes a reply was written with', function () {
    $note = Note::factory()->create();

    // The flattened text mf2 also offers would drop the anchor and the quote,
    // which is most of what a considered reply is made of.
    $body = 'Agreed. <a href="https://example.com/mine">I wrote this</a>.'
        .'<blockquote>Your words here.</blockquote>'
        .'<script>alert(1)</script>';

    $target = rtrim(config('app.url'), '/').$note->url();
    $document = verify($note, mentionSource($target, 'in-reply-to', $body))->content;

    expect($document[0]['markDefs'][0]['href'])->toBe('https://example.com/mine')
        ->and($document[1]['style'])->toBe('blockquote')
        // The script's contents are not prose and must not arrive as words.
        ->and(PortableText::plainText($document))->not->toContain('alert');
});

it('quotes a long response rather than republishing it', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $long = str_repeat('A sentence from somebody who marked their whole article as e-content. ', 30);
    $text = PortableText::plainText(verify($note, mentionSource($target, 'in-reply-to', $long))->content);

    // Whole-article e-content is common, and republishing it makes "Read it on
    // their site" meaningless as well as taking over the page.
    expect(mb_strlen($text))->toBeLessThanOrEqual(600)
        ->and($text)->toEndWith('…')
        // What is kept is a prefix of what they wrote, so the cut landed on a
        // word boundary and nothing was reordered on the way through.
        ->and(trim((string) preg_replace('/\s+/', ' ', $long)))
        ->toStartWith(rtrim($text, '…'));
});

it('prefers the summary the author wrote to a cut we made', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $long = str_repeat('Filler that runs well past the limit on its own. ', 30);
    $html = <<<HTML
    <div class="h-entry">
        <a class="u-in-reply-to" href="{$target}">re</a>
        <p class="p-summary">The short version, written by hand.</p>
        <div class="e-content"><p>{$long}</p></div>
    </div>
    HTML;

    expect(PortableText::plainText(verify($note, $html)->content))
        ->toBe('The short version, written by hand.');
});

it('leaves a response that already fits exactly as it was written', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $said = 'Short and to the point.';

    expect(PortableText::plainText(verify($note, mentionSource($target, 'in-reply-to', $said))->content))
        ->toBe($said);
});

it('keeps an image as the words describing it, and a linked image as a link', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $body = '<p>Here it is: <a href="https://example.com/post"><img src="https://example.com/a.png" alt="the dashboard"></a></p>';
    $document = verify($note, mentionSource($target, 'in-reply-to', $body))->content;

    expect(PortableText::plainText($document))->toContain('the dashboard')
        ->and($document[0]['markDefs'][0]['href'])->toBe('https://example.com/post');
});

it('drops an undescribed image rather than linking to nothing', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    // A span is only made where there is text, and a link is a mark on a span,
    // so an image nobody described cannot leave an anchor around an empty string.
    $body = '<p>Before <a href="https://example.com/post"><img src="https://example.com/a.png"></a> after.</p>';
    $document = verify($note, mentionSource($target, 'in-reply-to', $body))->content;

    expect($document[0]['markDefs'])->toBe([])
        ->and(PortableText::plainText($document))->toContain('Before')
        ->and(PortableText::plainText($document))->toContain('after.');
});

it('drops an underline rather than dressing text up as a link', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    // Underlined text is indistinguishable from a link in a response, so the
    // words are kept and the decoration is not.
    $body = '<p>Some <u>underlined</u> and <ins>inserted</ins> words.</p>';
    $document = verify($note, mentionSource($target, 'in-reply-to', $body))->content;

    expect(PortableText::plainText($document))->toBe('Some underlined and inserted words.');

    foreach ($document[0]['children'] as $span) {
        expect($span['marks'])->toBe([]);
    }
});

it('does not let a sender microformat become a property of our own citation', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    // mf2 hoists a nested p-name into the enclosing h-cite. Republishing the
    // sender's classes would make their article title our comment's name.
    $body = '<h1 class="p-name">Their article title</h1><p>My actual reply.</p>';
    $document = verify($note, mentionSource($target, 'in-reply-to', $body))->content;

    foreach ($document as $block) {
        foreach ($block['children'] as $span) {
            expect($span)->not->toHaveKey('class');
        }
    }

    expect(json_encode($document))->not->toContain('p-name');
});

it('does not print a gesture page title as though somebody said it', function (string $property, string $kind) {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    // The dominant shape for a gesture is author + name + url and no content
    // at all, so the page title is the only prose on offer. It is the name of
    // their post, not a remark about ours.
    $html = <<<HTML
    <div class="h-entry">
        <a class="p-author h-card" href="https://jan.systems">Jan</a>
        <h1 class="p-name">Some page title</h1>
        <a class="u-{$property}" href="{$target}">gesture</a>
    </div>
    HTML;

    verify($note, $html)->update(['status' => CommentStatus::Approved]);
    $response = Conversation::for($note)->responses[0];

    expect($response->kind)->toBe($kind)
        ->and($response->body)->toBeNull();
})->with([
    'like' => ['like-of', 'like'],
    'repost' => ['repost-of', 'repost'],
    'bookmark' => ['bookmark-of', 'bookmark'],
]);

it('keeps a source post title apart from its content', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    // The two are different claims: one names their post, the other is what
    // they wrote. Folding the name into content publishes a title as e-content.
    $html = <<<HTML
    <div class="h-entry">
        <a class="p-author h-card" href="https://jan.systems">Jan</a>
        <h1 class="p-name">Thoughts on slow software</h1>
        <a class="u-in-reply-to" href="{$target}">re</a>
    </div>
    HTML;

    $mention = verify($note, $html);

    expect($mention->title)->toBe('Thoughts on slow software')
        ->and($mention->content)->toBeNull();
});

it('takes the title and the content when a source marks up both', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $html = <<<HTML
    <div class="h-entry">
        <a class="p-author h-card" href="https://jan.systems">Jan</a>
        <h1 class="p-name">Thoughts on slow software</h1>
        <a class="u-in-reply-to" href="{$target}">re</a>
        <div class="e-content"><p>The bit I keep coming back to is the warm up.</p></div>
    </div>
    HTML;

    $mention = verify($note, $html);

    expect($mention->title)->toBe('Thoughts on slow software')
        ->and(PortableText::plainText($mention->content))
        ->toBe('The bit I keep coming back to is the warm up.');
});

it('refuses an implied name, which is the page text rather than a title', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    // An h-entry with nothing marked up inside it gets an implied name: the
    // element's whole text. Shown as a title that reads as nonsense.
    $sentence = str_repeat('Words that are plainly not a heading. ', 6);
    $html = <<<HTML
    <div class="h-entry">
        <a class="p-author h-card" href="https://jan.systems">Jan</a>
        <p>{$sentence}</p>
        <a href="{$target}">this</a>
    </div>
    HTML;

    expect(verify($note, $html)->title)->toBeNull();
});
