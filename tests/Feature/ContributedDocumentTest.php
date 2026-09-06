<?php

use App\Models\Comment;
use App\Models\Note;
use App\Support\PortableText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

/** A token issued far enough back to clear the minimum time-on-form. */
function contributedNonce(): string
{
    $nonce = Str::uuid()->toString();
    Cache::put('nonce:comment:'.$nonce, now()->subSeconds(30)->timestamp, 3600);

    return $nonce;
}

/** One Portable Text paragraph, with whatever marks and annotations are given. */
function doc(string $text, array $marks = [], array $markDefs = []): array
{
    return [[
        '_type' => 'block',
        '_key' => 'k1',
        'style' => 'normal',
        'markDefs' => $markDefs,
        'children' => [['_type' => 'span', '_key' => 's1', 'text' => $text, 'marks' => $marks]],
    ]];
}

/** A link annotation pointing wherever the test needs it to. */
function linkTo(string $href): array
{
    return [['_type' => 'link', '_key' => 'L1', 'href' => $href]];
}

function postDoc(int $noteId, array $body)
{
    return postJson("/comments/note/{$noteId}", [
        'author_name' => 'Jo',
        'body' => $body,
        'nonce' => contributedNonce(),
    ], ['REMOTE_ADDR' => '203.0.113.9']);
}

it('stores a document with the emphasis and links it allows', function () {
    $note = Note::factory()->create();

    postDoc($note->id, doc('Worth a read', ['strong', 'L1'], linkTo('https://example.com/x')))
        ->assertSuccessful();

    $body = Comment::query()->latest('id')->first()->body;

    expect($body[0]['children'][0]['marks'])->toBe(['strong', 'L1'])
        ->and($body[0]['markDefs'][0]['href'])->toBe('https://example.com/x');
});

it('refuses a link that is not http', function (string $href) {
    $note = Note::factory()->create();

    postDoc($note->id, doc('click', ['L1'], linkTo($href)))
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');

    expect(Comment::query()->count())->toBe(0);
})->with([
    'javascript:alert(1)',
    'JavaScript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'vbscript:msgbox(1)',
    'file:///etc/passwd',
]);

it('refuses a node the contributed renderer cannot draw', function (array $block) {
    $note = Note::factory()->create();

    postDoc($note->id, [$block])->assertStatus(422)->assertJsonValidationErrors('body');
})->with([
    'an image' => fn () => ['_type' => 'image', '_key' => 'k1', 'asset' => ['url' => 'https://example.com/a.png']],
    'a callout' => fn () => ['_type' => 'callout', '_key' => 'k1', 'children' => []],
    'a heading' => fn () => ['_type' => 'block', '_key' => 'k1', 'style' => 'h1', 'markDefs' => [], 'children' => []],
    'a list item' => fn () => ['_type' => 'block', '_key' => 'k1', 'style' => 'normal', 'listItem' => 'bullet', 'markDefs' => [], 'children' => []],
]);

it('refuses a mark naming an annotation that does not exist', function () {
    $note = Note::factory()->create();

    // Would render as unmarked text, so it is a malformed document rather than
    // a harmless one, and saying so beats storing something that lies.
    postDoc($note->id, doc('hello', ['nope']))->assertStatus(422);
});

it('refuses an annotation type it has no renderer for', function () {
    $note = Note::factory()->create();

    postDoc($note->id, doc('hi', ['L1'], [['_type' => 'internalLink', '_key' => 'L1', 'href' => 'https://example.com']]))
        ->assertStatus(422);
});

it('still accepts a plain string and stores it as a document', function () {
    $note = Note::factory()->create();

    postJson("/comments/note/{$note->id}", [
        'author_name' => 'Jo',
        'body' => 'Just words, from a client with no editor.',
        'nonce' => contributedNonce(),
    ], ['REMOTE_ADDR' => '203.0.113.9'])->assertSuccessful();

    $body = Comment::query()->latest('id')->first()->body;

    expect($body[0]['_type'])->toBe('block')
        ->and(PortableText::plainText($body))->toBe('Just words, from a client with no editor.');
});

it('measures length on the words, not the markup', function () {
    $note = Note::factory()->create();

    // 4,000 characters of text wrapped in marks would blow a naive limit on the
    // encoded document while being a perfectly ordinary comment.
    postDoc($note->id, doc(str_repeat('a', 3999), ['strong']))->assertSuccessful();
    postDoc($note->id, doc(str_repeat('a', 4001), ['strong']))->assertStatus(422);
});

it('keeps the spaces between a span and the one it is marked apart from', function () {
    $note = Note::factory()->create();

    // TrimStrings recurses into arrays, so an unexcepted body would store
    // "with" and "bold" with nothing between them and render them as one word.
    postDoc($note->id, [[
        '_type' => 'block',
        '_key' => 'k1',
        'style' => 'normal',
        'markDefs' => [],
        'children' => [
            ['_type' => 'span', '_key' => 's1', 'text' => 'Testing with ', 'marks' => []],
            ['_type' => 'span', '_key' => 's2', 'text' => 'formatting', 'marks' => ['strong']],
            ['_type' => 'span', '_key' => 's3', 'text' => ' applied.', 'marks' => []],
        ],
    ]])->assertSuccessful();

    expect(PortableText::plainText(Comment::query()->latest('id')->first()->body))
        ->toBe('Testing with formatting applied.');
});
