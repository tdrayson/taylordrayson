<?php

use App\Models\Note;
use App\Models\User;
use App\Support\PortableText;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/** A document of one block holding exactly $length readable characters. */
function noteOf(int $length): array
{
    return PortableText::fromPlainText(str_repeat('a', $length));
}

it('accepts a note at the limit and refuses one past it', function () {
    $this->postJson('/entries/note', ['content' => noteOf(Note::MAX_LENGTH)])->assertRedirect();
    $this->postJson('/entries/note', ['content' => noteOf(Note::MAX_LENGTH + 1)])
        ->assertJsonValidationErrors('content');

    expect(Note::count())->toBe(1);
});

it('measures readable text, so marking a word as a link does not spend the budget', function () {
    // Three spans where the plain version has one: the count must not change
    // because a link was added, or the ring would jump as you format.
    $document = [[
        '_type' => 'block',
        '_key' => 'k1',
        'style' => 'normal',
        'markDefs' => [['_key' => 'm1', '_type' => 'link', 'href' => 'https://example.com']],
        'children' => [
            ['_type' => 'span', '_key' => 's1', 'text' => str_repeat('a', Note::MAX_LENGTH - 10), 'marks' => []],
            ['_type' => 'span', '_key' => 's2', 'text' => str_repeat('b', 10), 'marks' => ['m1']],
        ],
    ]];

    $this->postJson('/entries/note', ['content' => $document])->assertRedirect();

    expect(PortableText::plainText(Note::sole()->content))->toHaveLength(Note::MAX_LENGTH);
});
