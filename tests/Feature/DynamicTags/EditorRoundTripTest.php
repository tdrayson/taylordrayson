<?php

use App\Models\Note;
use App\Models\User;
use App\Support\PortableText;

it('reparses a tag left as literal text back into a dynamicTag node', function () {
    // The literal `{tag options}` text form withTags() parses back into a
    // dynamicTag node: tag name, then each option as `key:value`, space
    // separated.
    $document = [PortableText::block('Logged {entries.count type:note period:2026}.')];

    $children = PortableText::withTags($document)[0]['children'];

    expect($children)->toHaveCount(3)
        ->and($children[0]['text'])->toBe('Logged ')
        ->and($children[1])->toMatchArray([
            '_type' => 'dynamicTag',
            'tag' => 'entries.count',
            'options' => ['type' => 'note', 'period' => '2026'],
        ])
        ->and($children[2]['text'])->toBe('.');
});

it('is a no-op on a document with no tokens', function () {
    $document = [PortableText::block('Nothing to see here.')];

    expect(PortableText::withTags($document))->toBe($document);
});

it('leaves an unregistered tag as literal text', function () {
    $document = [PortableText::block('A typo like {entriez.count} stays put.')];

    expect(PortableText::withTags($document))->toBe($document);
});

it('leaves a token split across two spans as literal text, rather than reassembling it', function () {
    // Accepted behaviour, not a bug: withTags() matches per span, not the
    // joined block text, because the editor PR makes this unreachable by
    // inserting tags as an atomic node. Do not "fix" this by joining spans.
    $document = [[
        '_type' => 'block',
        '_key' => 'b1',
        'style' => 'normal',
        'markDefs' => [],
        'children' => [
            ['_type' => 'span', '_key' => 's1', 'text' => 'Logged {entries.count ', 'marks' => []],
            ['_type' => 'span', '_key' => 's2', 'text' => 'type:note}.', 'marks' => ['strong']],
        ],
    ]];

    expect(PortableText::withTags($document))->toBe($document);
});

it('saves a tag typed into the editor as a dynamicTag node, not dead text', function () {
    $this->actingAs(User::factory()->create());

    $content = [[
        '_type' => 'block',
        '_key' => 'b1',
        'style' => 'normal',
        'markDefs' => [],
        'children' => [
            ['_type' => 'span', '_key' => 's1', 'text' => 'Logged {entries.count type:note}.', 'marks' => []],
        ],
    ]];

    $response = $this->post('/entries/note', ['content' => $content, 'slug' => 'editor-tag-note']);

    $response->assertRedirect();

    $note = Note::sole();
    $children = $note->content[0]['children'];

    expect($children[1])->toMatchArray([
        '_type' => 'dynamicTag',
        'tag' => 'entries.count',
        'options' => ['type' => 'note'],
    ]);
});
