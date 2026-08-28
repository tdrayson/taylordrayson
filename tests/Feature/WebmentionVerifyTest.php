<?php

use App\Actions\Webmentions\ParseMentionSource;
use App\Enums\CommentStatus;
use App\Enums\ReactionType;
use App\Enums\WebmentionKind;
use App\Jobs\VerifyWebmention;
use App\Models\Note;
use App\Models\Reaction;
use App\Models\Webmention;
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
        ->content->toBe('Nice one.')
        ->status->toBe(CommentStatus::Pending)
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

it('counts a like as a heart rather than an empty reply', function () {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    verify($note, mentionSource($target, 'like-of', ''));

    expect(Reaction::query()->where('reactable_id', $note->id)->value('type'))
        ->toBe(ReactionType::Love);
});

it('reads a single emoji reply as a reaction rather than a one-line reply', function (string $emoji, ?ReactionType $expected) {
    $note = Note::factory()->create();
    $target = rtrim(config('app.url'), '/').$note->url();

    $mention = verify($note, mentionSource($target, 'in-reply-to', $emoji));

    expect($mention->kind)->toBe(WebmentionKind::Reacji->value)
        ->and(Reaction::query()->where('reactable_id', $note->id)->value('type'))->toBe($expected);
})->with([
    'one we offer' => ["\u{1F602}", ReactionType::Haha],
    // Five codepoints joined by zero-width joiners, and a thumb carrying a skin
    // tone: one grapheme each, several codepoints each. The mb_strlen trap.
    'ZWJ sequence' => ["\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}", null],
    'skin tone' => ["\u{1F44D}\u{1F3FD}", null],
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
