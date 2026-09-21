<?php

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Note;
use App\Queries\Hub\Checks\HeldForModeration;
use App\Support\InteractionTarget;
use App\Support\PortableText;

function heldComment(string $status = 'pending'): Comment
{
    $note = Note::factory()->create(['occurred_at' => now()->subDay()]);

    return $note->comments()->create([
        'author_name' => 'Jo Bloggs',
        'body' => PortableText::fromPlainText('Is this thing on?'),
        'status' => $status,
    ]);
}

it('raises a held comment', function () {
    heldComment();

    $items = app(HeldForModeration::class)->items();

    expect($items)->toHaveCount(1)
        ->and($items[0]->kind)->toBe('comment')
        ->and($items[0]->title)->toContain('Jo Bloggs')
        ->and($items[0]->body)->toBe('Is this thing on?');
});

it('offers approve and reject, in that order', function () {
    heldComment();

    $actions = app(HeldForModeration::class)->items()[0]->toArray()['actions'];

    expect(array_column($actions, 'label'))->toBe(['Approve', 'Reject'])
        ->and($actions[1]['action'])->toBe('spam');
});

it('clears once the comment is approved', function () {
    heldComment(CommentStatus::Approved->value);

    expect(app(HeldForModeration::class)->items())->toBeEmpty();
});

it('names a note target by its card title', function () {
    $note = Note::factory()->create(['content' => PortableText::fromPlainText('Hello world')]);

    expect(InteractionTarget::titleFor($note))->toBe('Hello world');
});

it('falls back to a placeholder for a gone target', function () {
    expect(InteractionTarget::titleFor(null))->toBe('an entry that has since gone');
});
