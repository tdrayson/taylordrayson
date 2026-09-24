<?php

use App\Models\Note;
use App\Models\User;

it('labels a note with its opening words rather than its array cast', function () {
    Note::factory()->create([
        'content' => 'Having my Surgeon come up to me in Nando\'s is a very weird experience, but here we are.',
        'occurred_at' => '2026-06-15 09:00:00',
    ]);

    $this->actingAs(User::factory()->create());

    $label = collect($this->getJson('/mentions/search?q=Surgeon')->json('data'))
        ->firstWhere('kind', 'note')['label'];

    expect($label)->not->toBe('Array')
        ->and($label)->toStartWith('Having my Surgeon come up to me');
});
