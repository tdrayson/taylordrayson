<?php

use App\Enums\ResponseKind;
use App\Models\Article;
use App\Models\Citation;
use App\Models\Note;

it('lets two replies share one stored copy of a post', function () {
    $citation = Citation::factory()->create(['url' => 'https://example.com/post']);

    $note = Note::factory()->create(['citation_id' => $citation->id]);
    $article = Article::factory()->create(['citation_id' => $citation->id]);

    expect($note->citation->is($citation))->toBeTrue()
        ->and($article->citation->is($citation))->toBeTrue()
        ->and($citation->notes()->count())->toBe(1)
        ->and($citation->articles()->count())->toBe(1);
});

it('keeps a separate quote on each reply', function () {
    $citation = Citation::factory()->create(['url' => 'https://example.com/shared-post']);

    $one = Note::factory()->create(['response_kind' => ResponseKind::Reply, 'response_url' => $citation->url, 'citation_id' => $citation->id, 'response_quote' => 'The first passage.']);
    $two = Note::factory()->create(['response_kind' => ResponseKind::Reply, 'response_url' => $citation->url, 'citation_id' => $citation->id, 'response_quote' => 'A different passage.']);

    expect($one->fresh()->response_quote)->toBe('The first passage.')
        ->and($two->fresh()->response_quote)->toBe('A different passage.');
});

// The reply still exists and still links to what it answered; only the copy goes.
it('leaves the reply in place when its citation is deleted', function () {
    $citation = Citation::factory()->create();
    $note = Note::factory()->create(['citation_id' => $citation->id]);

    $citation->delete();

    expect($note->fresh())->not->toBeNull()
        ->and($note->fresh()->citation_id)->toBeNull();
});
