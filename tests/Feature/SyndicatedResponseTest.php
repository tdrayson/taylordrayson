<?php

use App\Enums\CommentStatus;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Note;
use App\Models\SyndicatedResponse;

it('hangs a syndicated response off the entry it belongs to', function () {
    $note = Note::factory()->create();

    $note->syndicatedResponses()->create([
        'source' => Source::Strava->value,
        'kind' => WebmentionKind::Like,
        'author_name' => 'Justin M.',
        'occurred_at' => now(),
    ]);

    $response = $note->syndicatedResponses()->sole();

    expect($response->kind)->toBe(WebmentionKind::Like)
        ->and($response->status)->toBe(CommentStatus::Approved)
        ->and($response->target->is($note))->toBeTrue();
});

// The morph names a type and an id, and nothing in the database stops it naming
// one that no longer exists, so the rows go with the entry.
it('sweeps its syndicated responses when the entry is deleted', function () {
    $note = Note::factory()->create();
    SyndicatedResponse::factory()->for($note, 'target')->create();

    $note->delete();

    expect(SyndicatedResponse::query()->count())->toBe(0);
});
