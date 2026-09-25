<?php

use App\Models\Note;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['queue.default' => 'sync']); // run media conversions inline so the srcset exists
    $this->actingAs(User::factory()->create());
});

it('marks a photo reviewed, not just un-reviewed', function () {
    $note = Note::factory()->create(['content' => 'A photo', 'occurred_at' => '2026-01-01 09:00:00']);
    $attachment = $note->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $subject = Subject::factory()->person()->create();
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 10, 'y' => 10]);

    $page = visit('/photos');
    $page->click('[aria-label^="View photo from"]');
    $page->click('[role="switch"]')->wait(1);

    // post() and delete() take options in a different shape; before the fix,
    // marking reviewed sent the delete shape to the post endpoint and 422'd,
    // so the switch would never flip on.
    $page->assertScript("document.querySelector('[role=\"switch\"]')?.getAttribute('aria-checked')", 'true');

    expect($attachment->fresh()->getCustomProperty('reviewed.subjects'))->not->toBeNull();
});
